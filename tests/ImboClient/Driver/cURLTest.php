<?php
/**
 * This file is part of the ImboClient package
 *
 * (c) Christer Edvartsen <cogo@starzinger.net>
 *
 * For the full copyright and license information, please view the LICENSE file that was
 * distributed with this source code.
 */

namespace ImboClient\Driver;

use ImboClient\Exception\ServerException,
    ImboClient\Driver\cURL\Wrapper,
    ReflectionProperty;

/**
 * @package Test suite
 * @author Christer Edvartsen <cogo@starzinger.net>
 */
class cURLTest extends \PHPUnit_Framework_TestCase {
    /**
     * The driver instance
     *
     * @var cURL
     */
    private $driver;

    /**
     * @var Wrapper
     */
    private $wrapper;

    /**
     * URL to the script that the tests should send requests to
     *
     * @var string
     */
    private $testUrl;

    /**
     * Setup the driver
     *
     * @covers ImboClient\Driver\cURL::__construct
     */
    public function setUp() {
        if (!IMBOCLIENT_ENABLE_TESTS) {
            $this->markTestSkipped('IMBOCLIENT_ENABLE_TESTS must be set to true to run these tests');
        }

        $this->driver  = new cURL();
        $this->testUrl = IMBOCLIENT_TESTS_URL;
        $this->wrapper = $this->getMock('ImboClient\Driver\cURL\Wrapper');
    }

    /**
     * Tear down the driver
     *
     * @covers ImboClient\Driver\cURL::__destruct
     */
    public function tearDown() {
        $this->driver = null;
        $this->wrapper = null;
    }

    /**
     * The driver must be able to POST data
     *
     * @covers ImboClient\Driver\cURL::post
     * @covers ImboClient\Driver\cURL::request
     */
    public function testCanPostDataUsingHttpPost() {
        $metadata = array(
            'foo' => 'bar',
            'bar' => 'foo',
        );
        $response = $this->driver->post($this->testUrl, json_encode($metadata));
        $this->assertInstanceOf('ImboClient\Http\Response\ResponseInterface', $response);
        $result = unserialize($response->getBody());
        $this->assertSame('POST', $result['method']);
        $this->assertSame($metadata, json_decode($result['data'], true));
    }

    /**
     * The driver must be able to put a file using HTTP PUT
     *
     * @covers ImboClient\Driver\cURL::put
     * @covers ImboClient\Driver\cURL::request
     */
    public function testCanPutAFileUsingHttpPut() {
        $response = $this->driver->put($this->testUrl, __FILE__);
        $this->assertInstanceOf('ImboClient\Http\Response\ResponseInterface', $response);
        $data = unserialize($response->getBody());
        $this->assertSame($data['md5'], md5_file(__FILE__));
    }

    /**
     * The driver must be able to put data using HTTP PUT
     *
     * @covers ImboClient\Driver\cURL::putData
     * @covers ImboClient\Driver\cURL::request
     */
    public function testCanPutDataUsingHttpPut() {
        $file = file_get_contents(__FILE__);
        $response = $this->driver->putData($this->testUrl, $file);
        $this->assertInstanceOf('ImboClient\Http\Response\ResponseInterface', $response);
        $result = unserialize($response->getBody());
        $this->assertSame('PUT', $result['method']);
        $this->assertSame($result['md5'], md5($file));
    }

    /**
     * The driver must be able to request a URL using HTTP GET
     *
     * @covers ImboClient\Driver\cURL::get
     * @covers ImboClient\Driver\cURL::request
     */
    public function testCanRequestAnUrlWithQueryParametersUsingHttpGet() {
        $url = $this->testUrl . '?foo=bar&bar=foo';
        $response = $this->driver->get($url);
        $this->assertInstanceOf('ImboClient\Http\Response\ResponseInterface', $response);
        $result = unserialize($response->getBody());
        $this->assertSame('GET', $result['method']);
        $this->assertSame(array('foo' => 'bar', 'bar' => 'foo'), $result['data']);
    }

    /**
     * The driver must be able to request a URL using HTTP HEAD
     *
     * @covers ImboClient\Driver\cURL::head
     * @covers ImboClient\Driver\cURL::request
     */
    public function testCanRequestAnUrlUsingHttpHead() {
        $response = $this->driver->head($this->testUrl);
        $this->assertInstanceOf('ImboClient\Http\Response\ResponseInterface', $response);
        $this->assertEmpty($response->getBody());
    }

    /**
     * The driver must be able to request a URL using HTTP DELETE
     *
     * @covers ImboClient\Driver\cURL::delete
     * @covers ImboClient\Driver\cURL::request
     */
    public function testCanRequestAnUrlUsingHttpDelete() {
        $response = $this->driver->delete($this->testUrl);
        $this->assertInstanceOf('ImboClient\Http\Response\ResponseInterface', $response);
        $result = unserialize($response->getBody());
        $this->assertSame('DELETE', $result['method']);
    }

    /**
     * The driver must time out if the server uses more time than what the driver accepts
     *
     * @expectedException ImboClient\Exception\RuntimeException
     * @expectedExceptionMessage An error occured. Request timed out during transfer (limit: 2s).
     * @covers ImboClient\Driver\cURL::get
     * @covers ImboClient\Driver\cURL::request
     */
    public function testTimesOutWhenTheServerTakesTooLongToRespond() {
        $url = $this->testUrl . '?sleep=3';
        $this->driver->get($url);
    }

    /**
     * The driver must be able to accept custom parameters through the constructor that will
     * override the default values
     *
     * @expectedException ImboClient\Exception\RuntimeException
     * @expectedExceptionMessage An error occured. Request timed out during transfer (limit: 1s).
     * @covers ImboClient\Driver\cURL::__construct
     * @covers ImboClient\Driver\cURL::get
     * @covers ImboClient\Driver\cURL::request
     */
    public function testAcceptsCustomParametersThroughConstructor() {
        $params = array(
            'timeout' => 1,
        );
        $driver = new cURL($params);
        $url = $this->testUrl . '?sleep=2';
        $driver->get($url);
    }

    /**
     * The driver must not include the Expect request header pr default
     *
     * @covers ImboClient\Driver\cURL::post
     * @covers ImboClient\Driver\cURL::request
     * @covers ImboClient\Driver\cURL::setRequestHeader
     */
    public function testDoesNotIncludeExpectHeaderPrDefault() {
        $postData = '{"some":"data"}';
        $url = $this->testUrl . '?headers';
        $response = $this->driver->post($url, $postData);
        $headers = unserialize($response->getBody());

        $this->assertArrayNotHasKey('HTTP_EXPECT', $headers);

        // Add a header and make the same request
        $this->assertSame($this->driver, $this->driver->setRequestHeader('Header', 'value'));

        $response = $this->driver->post($url, $postData);
        $headers = unserialize($response->getBody());

        $this->assertArrayNotHasKey('HTTP_EXPECT', $headers);
    }

    /**
     * The driver must support setting an additional request header
     *
     * @covers ImboClient\Driver\cURL::setRequestHeader
     */
    public function testCanSetAnAdditionalRequestHeader() {
        $this->assertSame($this->driver, $this->driver->setRequestHeader('Header', 'value'));
        $url = $this->testUrl . '?headers';
        $response = $this->driver->get($url);
        $headers = unserialize($response->getBody());

        $this->assertArrayHasKey('HTTP_HEADER', $headers);
        $this->assertSame('value', $headers['HTTP_HEADER']);
    }

    /**
     * The driver must support setting multiple additional request header
     *
     * @covers ImboClient\Driver\cURL::setRequestHeader
     * @covers ImboClient\Driver\cURL::setRequestHeaders
     */
    public function testCanSetMultipleAdditionalRequestHeaders() {
        $this->assertSame($this->driver, $this->driver->setRequestHeaders(array(
            'Header' => 'value',
            'User-Agent' => 'ImboClient',
        )));
        $url = $this->testUrl . '?headers';
        $response = $this->driver->get($url);
        $headers = unserialize($response->getBody());

        $this->assertArrayHasKey('HTTP_HEADER', $headers);
        $this->assertArrayHasKey('HTTP_USER_AGENT', $headers);

        $this->assertSame('value', $headers['HTTP_HEADER']);
        $this->assertSame('ImboClient', $headers['HTTP_USER_AGENT']);
    }

    /**
     * The driver must follow redirects
     *
     * @covers ImboClient\Driver\cURL::get
     * @covers ImboClient\Driver\cURL::request
     */
    public function testFollowsRedirects() {
        $url = $this->testUrl . '?redirect=2';
        $response = unserialize($this->driver->get($url)->getBody());

        $this->assertEquals(0, $response['data']['redirect']);
    }

    /**
     * The driver must throw an exception when the server responds with an error as well as make
     * the response available through the exception
     *
     * @expectedException ImboClient\Exception\ServerException
     * @expectedExceptionMessage Bad Request
     * @expectedExceptionCode 400
     * @covers ImboClient\Driver\cURL::get
     * @covers ImboClient\Driver\cURL::request
     * @covers ImboClient\Exception\ServerException::getResponse
     */
    public function testThrowsExceptionWhenTheServerRespondsWithAClientErrorAndMakesTheResponseAvailableThroughTheException() {
        $url = $this->testUrl . '?clientError';

        try {
            $this->driver->get($url);
            $this->fail('Expected exception');
        } catch (ServerException $e) {
            $this->assertInstanceOf('ImboClient\Http\Response\ResponseInterface', $e->getResponse());
            throw $e;
        }
    }

    /**
     * The driver must throw an exception when the server responds with an error as well as make
     * the response available through the exception
     *
     * @expectedException ImboClient\Exception\ServerException
     * @expectedExceptionMessage Internal Server Error
     * @expectedExceptionCode 500
     * @covers ImboClient\Driver\cURL::get
     * @covers ImboClient\Driver\cURL::request
     * @covers ImboClient\Exception\ServerException::getResponse
     */
    public function testThrowsExceptionWhenTheServerRespondsWithAServerErrorAndMakesTheResponseAvailableThroughTheException() {
        $url = $this->testUrl . '?serverError';

        try {
            $this->driver->get($url);
            $this->fail('Expected exception');
        } catch (ServerException $e) {
            $this->assertInstanceOf('ImboClient\Http\Response\ResponseInterface', $e->getResponse());
            throw $e;
        }
    }

    /**
     * The driver must not include duplicate request headers
     *
     * @link https://github.com/imbo/imboclient-php/issues/52
     * @covers ImboClient\Driver\cURL::setRequestHeader
     * @covers ImboClient\Driver\cURL::setRequestHeaders
     */
    public function testDoesNotSendDuplicateRequestHeaders() {
        $this->driver->setRequestHeader('Foo', 'foo1');
        $this->driver->setRequestHeader('Foo', 'foo2');
        $this->driver->setRequestHeaders(array(
            'Bar' => 'bar1',
            'Bar' => 'bar2',
            'Foo' => 'foo3',
        ));

        $property = new ReflectionProperty('ImboClient\Driver\cURL', 'headers');
        $property->setAccessible(true);

        $response = $this->driver->get($this->testUrl . '?headers');
        $headers = unserialize($response->getBody());

        $this->assertArrayHasKey('HTTP_FOO', $headers);
        $this->assertArrayHasKey('HTTP_BAR', $headers);
        $this->assertSame('foo3', $headers['HTTP_FOO']);
        $this->assertSame('bar2', $headers['HTTP_BAR']);
    }

    /**
     * The driver must merge custom cURL options with the default ones provided to the constructor
     *
     * @covers ImboClient\Driver\cURL::__construct
     */
    public function testAcceptsCustomCurlParametersThroughConstructor() {
        $this->wrapper->expects($this->once())->method('setOptArray')->with($this->callback(function($options) {
            return $options[CURLOPT_TIMEOUT] == 666 && $options[CURLOPT_CONNECTTIMEOUT] == 333;
        }));

        $driver = new cURL(array(), array(
            CURLOPT_TIMEOUT => 666,
            CURLOPT_CONNECTTIMEOUT => 333,
        ), $this->wrapper);
    }

    /**
     * The driver must set a default error message when the server responds with an error and an
     * empty body (typically a response to a HEAD request)
     *
     * @expectedException ImboClient\Exception\ServerException
     * @expectedExceptionMessage Empty body
     * @expectedExceptionCode 500
     * @covers ImboClient\Driver\cURL::get
     * @covers ImboClient\Driver\cURL::request
     */
    public function testSetsADefaultErrorMessageWhenTheServerRespondsWithAnErrorAndAnEmptyResponseBody() {
        $url = $this->testUrl . '?serverError&emptyBody';

        $this->driver->get($url);
    }

    /**
     * Make sure that the driver does not set some of the SSL options if they don't have any values
     *
     * @see https://github.com/imbo/imboclient-php/issues/68
     * @covers ImboClient\Driver\cURL::request
     */
    public function testDoesNotSetEmptySslOptions() {
        $handle = 'curlhandle';
        $url = 'https://someurl';

        $this->wrapper->expects($this->once())->method('copy')->will($this->returnValue($handle));
        $this->wrapper->expects($this->at(0))->method('setOptArray')->with($this->isType('array'));
        // The next index is two because the counter is bumped for all methods called on the mock,
        // and the copy method is called between the first and second setOptArray
        $this->wrapper->expects($this->at(2))->method('setOptArray')->with(
            array(CURLOPT_HTTPGET => true),
            $handle
        );
        $this->wrapper->expects($this->at(3))->method('setOptArray')->with(array(
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_URL => $url,
        ), $handle);
        $this->wrapper->expects($this->once())->method('setOpt');
        $this->wrapper->expects($this->once())->method('exec')->with($handle)->will($this->returnValue("HTTP/1.1 200 OK\r\n\r\ncontent"));
        $this->wrapper->expects($this->any())->method('getInfo');

        $curl = new cURL(array('sslVerifyPeer' => false, 'sslVerifyHost' => 0), array(), $this->wrapper);
        $this->assertInstanceOf('ImboClient\Http\Response\Response', $curl->get($url));
    }

    /**
     * Make sure that the driver sets all SSL options when they have been given values
     *
     * @covers ImboClient\Driver\cURL::request
     */
    public function testSetSsslOptionsWhenAValueHasBeenSpecified() {
        $handle = 'curlhandle';
        $url = 'https://someurl';

        $this->wrapper->expects($this->once())->method('copy')->will($this->returnValue($handle));
        $this->wrapper->expects($this->at(0))->method('setOptArray')->with($this->isType('array'));
        // The next index is two because the counter is bumped for all methods called on the mock,
        // and the copy method is called between the first and second setOptArray
        $this->wrapper->expects($this->at(2))->method('setOptArray')->with(
            array(CURLOPT_HTTPGET => true),
            $handle
        );
        $this->wrapper->expects($this->at(3))->method('setOptArray')->with(array(
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 1,
            CURLOPT_CAPATH => '/some/path',
            CURLOPT_CAINFO => 'some info',
            CURLOPT_URL => $url,
        ), $handle);
        $this->wrapper->expects($this->once())->method('setOpt');
        $this->wrapper->expects($this->once())->method('exec')->with($handle)->will($this->returnValue("HTTP/1.1 200 OK\r\n\r\ncontent"));
        $this->wrapper->expects($this->any())->method('getInfo');

        $curl = new cURL(array(
            'sslCaPath' => '/some/path',
            'sslCaInfo' => 'some info',
            'sslVerifyPeer' => true,
            'sslVerifyHost' => 1,
        ), array(), $this->wrapper);
        $this->assertInstanceOf('ImboClient\Http\Response\Response', $curl->get($url));
    }

    /**
     * Make sure that the driver throws an exception if the connect timeout limit has been exceeded
     *
     * @expectedException ImboClient\Exception\RuntimeException
     * @expectedExceptionMessage An error occured. Request timed out while connecting (limit: 20s).
     * @covers ImboClient\Driver\cURL::request
     */
    public function testThrowsExceptionWhenConnectTimeoutIsHigherThanAllowed() {
        $handle = 'curlhandle';
        $url = 'http://someurl';

        $this->wrapper->expects($this->once())->method('copy')->will($this->returnValue($handle));
        $this->wrapper->expects($this->once())->method('exec')->with($handle)->will($this->returnValue(false));
        $this->wrapper->expects($this->any())->method('getInfo')->will($this->returnCallback(function($opt) {
            if ($opt === CURLINFO_CONNECT_TIME) {
                return 30;
            }

            return null;
        }));

        $curl = new cURL(array(
            'connectTimeout' => 20,
        ), array(), $this->wrapper);
        $curl->get($url);
    }

    /**
     * Make sure that the driver throws an exception if an unknown error occurs
     *
     * @expectedException ImboClient\Exception\RuntimeException
     * @expectedExceptionMessage An error occured. Could not complete request (Response code: 500).
     * @covers ImboClient\Driver\cURL::request
     */
    public function testThrowsExceptionWhenUndefinedErrorOccurs() {
        $handle = 'curlhandle';
        $url = 'http://someurl';

        $this->wrapper->expects($this->once())->method('copy')->will($this->returnValue($handle));
        $this->wrapper->expects($this->once())->method('exec')->with($handle)->will($this->returnValue(false));
        $this->wrapper->expects($this->any())->method('getInfo')->will($this->returnCallback(function($opt) {
            if ($opt === CURLINFO_HTTP_CODE) {
                return 500;
            }

            return null;
        }));

        $curl = new cURL(array(), array(), $this->wrapper);
        $curl->get($url);
    }

    /**
     * Make sure that the driver handles incorrectly serialized data in the response
     *
     * @expectedException ImboClient\Exception\ServerException
     * @expectedExceptionMessage Invalid response body. Expected JSON serialized data
     * @expectedExceptionCode 404
     * @covers ImboClient\Driver\cURL::request
     */
    public function testGracefullyHandlesNonJsonResponseBodies() {
        $response  = "HTTP/1.1 404 Not found\r\n";
        $response .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
        $response .= "<html><head></head><body>Not found</body></html>";

        $wrapper = $this->getMock('ImboClient\Driver\cURL\Wrapper');
        $wrapper->expects($this->once())->method('exec')->will($this->returnValue($response));
        $wrapper->expects($this->any())->method('getInfo')->will($this->returnCallback(function($const, $handle) {
            if ($const === CURLINFO_HTTP_CODE) {
                return 404;
            }
        }));
        $driver = new cURL(array(), array(), $wrapper);
        $driver->get('http://example.com/');
    }
}

