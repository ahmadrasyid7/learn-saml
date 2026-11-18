<?php

namespace App\Controller;

use DateTimeImmutable;
use DateTimeZone;
use SimpleSAML\SAML2\Constants as SAML2;
use SimpleSAML\SAML2\Type\SAMLAnyURIValue;
use SimpleSAML\SAML2\Type\SAMLDateTimeValue;
use SimpleSAML\SAML2\Type\SAMLStringValue;
use SimpleSAML\SAML2\XML\saml\Issuer;
use SimpleSAML\SAML2\XML\samlp\AuthnRequest;
use SimpleSAML\XML\Type\IDValue;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class LoginController
{
    public function __construct(
        #[Autowire(env: 'ENTITY_ID')] private readonly string $spEntityId,
        #[Autowire(env: 'BASE_URL')] private readonly string $spBaseUrl,
        #[Autowire(env: 'IDP_SSO_URL')] private readonly string $idpSsoUrl,
    ) {
    }

    #[Route('/login', name: 'sp_login', methods: ['GET'])]
    public function __invoke(Request $request): RedirectResponse
    {
        $requestId = '_' . bin2hex(random_bytes(20));
        $relayState = bin2hex(random_bytes(32));

        $authnRequest = new AuthnRequest(
            id: IDValue::fromString($requestId),
            issueInstant: SAMLDateTimeValue::fromDateTime(
                new DateTimeImmutable('now', new DateTimeZone('UTC')),
            ),
            assertionConsumerServiceURL: SAMLAnyURIValue::fromString(
                rtrim($this->spBaseUrl, '/') . '/acs',
            ),
            protocolBinding: SAMLAnyURIValue::fromString(SAML2::BINDING_HTTP_POST),
            issuer: new Issuer(SAMLStringValue::fromString($this->spEntityId)),
            destination: SAMLAnyURIValue::fromString($this->idpSsoUrl),
        );

        $xml = $authnRequest->toXML()->ownerDocument?->saveXML();
        if (!is_string($xml)) {
            throw new \RuntimeException('Unable to serialize the SAML authentication request.');
        }

        $deflatedRequest = gzdeflate($xml);
        if ($deflatedRequest === false) {
            throw new \RuntimeException('Unable to deflate the SAML authentication request.');
        }

        $session = $request->getSession();
        $session->set('saml_authn_request_id', $requestId);
        $session->set('saml_relay_state', $relayState);

        $redirectUrl = $this->idpSsoUrl . '?' . http_build_query(
            [
                'SAMLRequest' => base64_encode($deflatedRequest),
                'RelayState' => $relayState,
            ],
            '',
            '&',
            PHP_QUERY_RFC3986,
        );

        return new RedirectResponse($redirectUrl);
    }
}
