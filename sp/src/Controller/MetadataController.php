<?php

namespace App\Controller;

use SimpleSAML\SAML2\Constants as SAML2;
use SimpleSAML\SAML2\Type\EntityIDValue;
use SimpleSAML\SAML2\Type\KeyTypesValue;
use SimpleSAML\SAML2\Type\SAMLAnyURIListValue;
use SimpleSAML\SAML2\Type\SAMLAnyURIValue;
use SimpleSAML\SAML2\XML\md\AssertionConsumerService;
use SimpleSAML\SAML2\XML\md\EntityDescriptor;
use SimpleSAML\SAML2\XML\md\KeyDescriptor;
use SimpleSAML\SAML2\XML\md\KeyTypesEnum;
use SimpleSAML\SAML2\XML\md\SPSSODescriptor;
use SimpleSAML\XMLSchema\Type\BooleanValue;
use SimpleSAML\XMLSchema\Type\UnsignedShortValue;
use SimpleSAML\XMLSecurity\CryptoEncoding\PEM;
use SimpleSAML\XMLSecurity\XML\ds\KeyInfo;
use SimpleSAML\XMLSecurity\XML\ds\X509Certificate;
use SimpleSAML\XMLSecurity\XML\ds\X509Data;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MetadataController
{
    public function __construct(
        #[Autowire(env: 'ENTITY_ID')] private readonly string $entityId,
        #[Autowire(env: 'BASE_URL')] private readonly string $baseUrl,
    ) {
    }

    #[Route('/metadata', name: 'sp_metadata', methods: ['GET'])]
    public function metadata(): Response
    {
        return new Response(
            $this->buildMetadataXml(),
            headers: ['Content-Type' => 'application/samlmetadata+xml; charset=utf-8'],
        );
    }

    // Same document, served as text/xml so browsers render it instead of downloading it.
    #[Route('/metadata/view', name: 'sp_metadata_view', methods: ['GET'])]
    public function view(): Response
    {
        return new Response(
            $this->buildMetadataXml(),
            headers: ['Content-Type' => 'text/xml; charset=utf-8'],
        );
    }

    private function buildMetadataXml(): string
    {
        $certificatePem = PEM::fromFile('/certs/own/sp.crt');
        if (
            $certificatePem->type() !== PEM::TYPE_CERTIFICATE
            || openssl_x509_read($certificatePem->string()) === false
        ) {
            throw new \RuntimeException('The SP certificate is not a valid X.509 certificate.');
        }

        $certificate = X509Certificate::fromString(
            base64_encode($certificatePem->data()),
        );
        $keyDescriptor = new KeyDescriptor(
            new KeyInfo([
                new X509Data([$certificate]),
            ]),
            KeyTypesValue::fromEnum(KeyTypesEnum::SIGNING),
        );
        $spDescriptor = new SPSSODescriptor(
            assertionConsumerService: [
                new AssertionConsumerService(
                    UnsignedShortValue::fromInteger(0),
                    SAMLAnyURIValue::fromString(SAML2::BINDING_HTTP_POST),
                    SAMLAnyURIValue::fromString(rtrim($this->baseUrl, '/') . '/acs'),
                    BooleanValue::fromBoolean(true),
                ),
            ],
            protocolSupportEnumeration: SAMLAnyURIListValue::fromString(SAML2::NS_SAMLP),
            authnRequestsSigned: BooleanValue::fromBoolean(false),
            wantAssertionsSigned: BooleanValue::fromBoolean(true),
            keyDescriptors: [$keyDescriptor],
        );
        $metadata = new EntityDescriptor(
            entityId: EntityIDValue::fromString($this->entityId),
            roleDescriptor: [$spDescriptor],
        );

        $xml = $metadata->toXML()->ownerDocument?->saveXML();
        if (!is_string($xml)) {
            throw new \RuntimeException('Unable to serialize SP metadata as XML.');
        }

        return $xml;
    }
}
