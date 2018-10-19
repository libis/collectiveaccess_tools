<?php
/**
 * SAML 2.0 remote IdP metadata for SimpleSAMLphp.
 *
 * Remember to remove the IdPs you don't use from this file.
 *
 * See: https://simplesamlphp.org/docs/stable/simplesamlphp-reference-idp-remote 
 */


$metadata['https://engine.connect.surfconext.nl/authentication/idp/metadata'] = array(
    'name' => array(
        'en' => 'SURFconext',
        'nl' => 'SURFconext',
    ),
    'AssertionConsumerService' => 'https://test.collectiveaccess.tudelft.nl/ca_tudelft_test/index.php/Dashboard/Index',
    'SingleSignOnService'  => 'https://engine.connect.surfconext.nl/authentication/idp/single-sign-on',
    'certificate'          => 'surfconext.pem',
);
