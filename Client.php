<?php

/**
 * qal/flickr library
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 *
 * @copyright Copyright (c) 2012 Nicolas Bazire <nicolas.bazire@gmail.com> (http://qal.github.com/flickr/)
 * @license   http://qal.github.com/flickr/license
 */

namespace Qal\Api\Flickr;

use Guzzle\Http\Plugin\OauthPlugin;
use Guzzle\Service\Client as GuzzleClient;
use Guzzle\Service\Description\ServiceDescription;
use Guzzle\Service\Inspector;

/**
 * Service class for interacting with the Flickr API.
 *
 * This class uses by default a secure channel to access Flickr services.
 *
 * @author Nicolas Bazire <nicolas.bazire@gmail.com>
 */

class Client extends GuzzleClient
{
    /**
     * Factory method to create a new Client
     *
     * @param array|Collection $config Configuration data. Array keys:
     *    base_url - Base URL of web service
     *    api_key  - Consumer API key
     *    secret   - Consumer secret
     *
     * @return Qal\Api\Flickr\Client
     */
    public static function factory($config = array())
    {
        $default = array(
            'base_url' => 'https://secure.flickr.com/services/rest/?api_key={api_key}',
        );

        $required = array('api_key', 'base_url', 'secret');
        $config = Inspector::prepareConfig($config, $default, $required);

        $client = new self($config->get('base_url'));
        $client->setConfig($config);
        $client->setDescription(ServiceDescription::factory(__DIR__.'/client.xml'));

        if ($config->hasKey('oauth_token') && $config->hasKey('oauth_token_secret')) {
            $client->addOauth($config->get('oauth_token'), $config->get('oauth_token_secret'));
        }

        return $client;
    }

    /**
     * Easing API methods calls.
     *
     * To call an API method, simply replace dots by underscores in its name,
     * and use a key/value array to pass arguments.
     *
     * Example:
     *
     * <code>
     * $client->test_login(); // equivalent to $client->execute($client->getCommand('test.login'));
     * $client->auth_oauth_checkToken(array(
     *     'oauth_token' => 'zdi3293jzdqlz1932jdzk'
     * ));
     * </code>
     *
     * @return \SimpleXMLElement
     */
    public function __call($method, $args = null)
    {
        $method = str_replace('_', '.', $method);
        $args = isset($args[0]) ? $args[0] : array();
        $command = $this->getCommand($method, $args);

        return $this->execute($command);
    }

    /**
     * Utility function to retrieve an OAuth request token.
     *
     * @param string $callback_url Callback URL where the user will be redirected
     *     once he has authorized the application, use only for web apps.
     *
     * @throws \OAuthException If a problem occurs while fetchning the request token
     *
     * @return array OAuth params (oauth_callback_confirmed, oauth_token, oauth_secret)
     */
    public function getRequestToken($callback_url = '')
    {
        $config = $this->getConfig();

        $oauth = new \OAuth($config->get('api_key'), $config->get('secret'));
        $oauth_params = $oauth->getRequestToken('https://secure.flickr.com/services/oauth/request_token', $callback_url);

        if (!isset($oauth_params['oauth_callback_confirmed']) || !$oauth_params['oauth_callback_confirmed']) {
            throw new \OAuthException('OAuth callback not confirmed, failed to get request token');
        }

        return $oauth_params;
    }

    /**
     * Utility function to build an authorization URL with a request token.
     *
     * @param string $oauth_request_token OAuth request token
     *
     * @return string The authorization URL where the user should be redirected
     */
    public function getAuthorizeUrl($oauth_request_token)
    {
        $config = $this->getConfig();

        return sprintf('https://secure.flickr.com/services/oauth/authorize?oauth_token=%s', $oauth_request_token);
    }

    /**
     * Utility function to exchange a request token for an access token.
     *
     * @param string $oauth_request_token OAuth request token
     * @param string $oauth_request_token_secret OAuth request token secret
     * @param string $oauth_verifier OAuth verifier
     *
     * @throws \OAuthException If a problem occurs while exchanging the request token for an access token
     *
     * @return array OAuth params (oauth_token, oauth_token_secret)
     */
    public function getAccessToken($oauth_request_token, $oauth_request_token_secret, $oauth_verifier)
    {
        $config = $this->getConfig();

        $oauth = new \OAuth($config->get('api_key'), $config->get('secret'));
        $oauth->setToken($oauth_request_token, $oauth_request_token_secret);
        $oauth_params = $oauth->getAccessToken('https://secure.flickr.com/services/oauth/access_token', null, $oauth_verifier);

        $this->addOauth($oauth_params['oauth_token'], $oauth_params['oauth_token_secret']);

        return $oauth_params;
    }

    /**
     * Internal method to add OAuth authentication to API calls.
     *
     * If the application is already in possession of an access token it must
     * add it as an argument to the {@see factory()} method.
     *
     * @param string $oauth_token OAuth access token
     * @param string $oauth_token_secret OAuth access token secret
     */
    protected function addOauth($oauth_token, $oauth_token_secret)
    {
        $config = $this->getConfig();

        $this->getEventDispatcher()->addSubscriber(new OauthPlugin(array(
            'consumer_key'    => $config->get('api_key'),
            'consumer_secret' => $config->get('secret'),
            'token'           => $oauth_token,
            'token_secret'    => $oauth_token_secret,
        )));
    }
}
