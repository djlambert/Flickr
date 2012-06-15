<?php

namespace Qal\Api\Flickr\Tests;

use Guzzle\Service\Command\CommandSet;

use Qal\Api\Flickr;

class ClientTest extends \Guzzle\Tests\GuzzleTestCase
{
    public function getClient(array $access = array())
    {
        if (empty($access)) {
            return Flickr\Client::factory(array(
                'api_key' => $GLOBALS['api_key'],
                'secret'  => $GLOBALS['secret'],
            ));
        }

        return Flickr\Client::factory(array(
            'api_key'            => $GLOBALS['api_key'],
            'secret'             => $GLOBALS['secret'],
            'oauth_token'        => $access['oauth_token'],
            'oauth_token_secret' => $access['oauth_token_secret'],
        ));
    }

    public function testClientFactory()
    {
        $client = $this->getClient();
        $config = $client->getConfig();

        $this->assertInstanceOf('\Qal\Api\Flickr\Client', $client);
        $this->assertEquals('https://secure.flickr.com/services/rest/?api_key='.$GLOBALS['api_key'], $client->expandTemplate($config->get('base_url')));
        $this->assertEquals($GLOBALS['api_key'], $config->get('api_key'));
        $this->assertEquals($GLOBALS['secret'], $config->get('secret'));
    }

    /**
     * @group oauth
     */
    public function testGetRequestToken()
    {
        $client = $this->getClient();
        $request = $client->getRequestToken();

        $this->assertArrayHasKey('oauth_callback_confirmed', $request);
        $this->assertEquals('true', $request['oauth_callback_confirmed']);
        $this->assertArrayHasKey('oauth_token', $request);
        $this->assertArrayHasKey('oauth_token_secret', $request);

        return $request;
    }

    /**
     * @group oauth
     * @depends testGetRequestToken
     */
    public function testGetAuthorizeUrl(array $request)
    {
        $client = $this->getClient();
        $url = $client->getAuthorizeUrl($request['oauth_token'])."\n";

        $this->assertStringStartsWith('https://secure.flickr.com/services/oauth/authorize?oauth_token=', $url);

        echo "\n Click here then copy/paste the verification code : $url";

        return $request;
    }

    /**
     * @group oauth
     * @depends testGetAuthorizeUrl
     */
    public function testGetAccessToken(array $request)
    {
        $client = $this->getClient();
        $verification_token = trim(fgets(STDIN));
        $access = $client->getAccessToken($request['oauth_token'], $request['oauth_token_secret'], $verification_token);

        $this->assertArrayHasKey('fullname', $access);
        $this->assertArrayHasKey('oauth_token', $access);
        $this->assertArrayHasKey('oauth_token_secret', $access);
        $this->assertArrayHasKey('user_nsid', $access);
        $this->assertArrayHasKey('username', $access);

        return $access;
    }

    /**
     * @group networked
     * @depends testGetAccessToken
     */
    public function testActivityUserComments(array $access)
    {
        $thiz = $this;
        $set = new CommandSet(array(
            $this->getClient($access)
            ->getCommand('activity.userComments')
            ->setOnComplete(function ($cmd) use ($thiz) {
                $this->assertEquals('1', $cmd->getResult()->items['page']);
                $this->assertEquals('10', $cmd->getResult()->items['perpage']);
            }),
            $this->getClient($access)
            ->getCommand('activity.userComments', array(
                'page'     => 9,
                'per_page' => 47,
            ))
            ->setOnComplete(function ($cmd) use ($thiz) {
                $this->assertEquals('9', $cmd->getResult()->items['page']);
                $this->assertEquals('47', $cmd->getResult()->items['perpage']);
            }),
        ));

        $set->execute();
    }

    /**
     * @group networked
     * @depends testGetAccessToken
     */
    public function testActivityUserPhotos(array $access)
    {
        $rsp = $this->getClient($access)->activity_userPhotos();
        $this->assertEquals($rsp->items['page'], '1');
        $this->assertEquals($rsp->items['perpage'], '10');

        $rsp = $this->getClient($access)->activity_userPhotos(array(
            'timeframe' => '2d',
            'page'      => 3,
            'per_page'  => 36,
        ));

        $this->assertEquals($rsp->items['page'], '3');
        $this->assertEquals($rsp->items['perpage'], '36');

        $rsp = $this->getClient($access)->activity_userPhotos(array(
            'timeframe' => '6h',
        ));
    }

    /**
     * @group networked
     * @depends testGetAccessToken
     */
    public function testLogin(array $access)
    {
        $rsp = $this->getClient($access)->test_login();
        $this->assertEquals($rsp['stat'], 'ok');
    }

    /**
     * @group networked
     * @depends testGetAccessToken
     */
    public function testNull(array $access)
    {
        $rsp = $this->getClient($access)->test_null();
        $this->assertEquals($rsp['stat'], 'ok');
    }
}
