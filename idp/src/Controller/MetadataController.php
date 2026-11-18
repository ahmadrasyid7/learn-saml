<?php

namespace App\Controller;

use SimpleSAML\SAML2\Constants as SAML2;
use SimpleSAML\SAML2\Type\EntityIDValue;
use SimpleSAML\SAML2\Type\KeyTypesValue;
use SimpleSAML\SAML2\Type\SAMLAnyURIListValue;
use SimpleSAML\SAML2\Type\SAMLAnyURIValue;
use SimpleSAML\SAML2\XML\md\EntityDescriptor;
use SimpleSAML\SAML2\XML\md\IDPSSODescriptor;
use SimpleSAML\SAML2\XML\md\KeyDescriptor;
use SimpleSAML\SAML2\XML\md\KeyTypesEnum;
use SimpleSAML\SAML2\XML\md\SingleSignOnService;
use SimpleSAML\XMLSchema\Type\BooleanValue;
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

    #[Route('/metadata', name: 'idp_metadata', methods: ['GET'])]
    public function metadata(): Response
    {
        return new Response(
            $this->buildMetadataXml(),
            headers: ['Content-Type' => 'application/samlmetadata+xml; charset=utf-8'],
        );
    }

    #[Route('/metadata/view', name: 'idp_metadata_view', methods: ['GET'])]
    public function view(): Response
    {
        return new Response(
            $this->buildMetadataXml(),
            headers: ['Content-Type' => 'text/xml; charset=utf-8'],
        );
    }

    private function buildMetadataXml(): string
    {
        $certificatePem = PEM::fromFile('/certs/own/idp.crt');
        if (
            $certificatePem->type() !== PEM::TYPE_CERTIFICATE
            || openssl_x509_read($certificatePem->string()) === false
        ) {
            throw new \RuntimeException('The IdP certificate is not a valid X.509 certificate.');
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
        $idpDescriptor = new IDPSSODescriptor(
            singleSignOnService: [
                new SingleSignOnService(
                    SAMLAnyURIValue::fromString(SAML2::BINDING_HTTP_REDIRECT),
                    SAMLAnyURIValue::fromString(rtrim($this->baseUrl, '/') . '/sso'),
                ),
            ],
            protocolSupportEnumeration: SAMLAnyURIListValue::fromString(SAML2::NS_SAMLP),
            wantAuthnRequestsSigned: BooleanValue::fromBoolean(false),
            keyDescriptor: [$keyDescriptor],
        );
        $metadata = new EntityDescriptor(
            entityId: EntityIDValue::fromString($this->entityId),
            roleDescriptor: [$idpDescriptor],
        );

        $xml = $metadata->toXML()->ownerDocument?->saveXML();
        if (!is_string($xml)) {
            throw new \RuntimeException('Unable to serialize IdP metadata as XML.');
        }

        return $xml;
    }
}
