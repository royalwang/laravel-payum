<?php

use \Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Request;
use Illuminate\Session\SessionManager;
use Mockery as m;
use Payum\Core\Bridge\Symfony\ReplyToSymfonyResponseConverter;
use Payum\Core\GatewayInterface;
use Payum\Core\Payum;
use Payum\Core\Reply\HttpResponse;
use Payum\Core\Reply\ReplyInterface;
use Payum\Core\Request\Authorize;
use Payum\Core\Request\Capture;
use Payum\Core\Request\GetHumanStatus;
use Payum\Core\Request\Notify;
use Payum\Core\Request\Payout;
use Payum\Core\Request\Refund;
use Payum\Core\Request\Sync;
use Payum\Core\Security\HttpRequestVerifierInterface;
use Payum\Core\Security\TokenInterface;
use Recca0120\LaravelPayum\Service\Payment;

class PaymentTest extends PHPUnit_Framework_TestCase
{
    public function tearDown()
    {
        m::close();
    }

    public function testGetPayum()
    {
        /*
        |------------------------------------------------------------
        | Set
        |------------------------------------------------------------
        */

        $payum = m::mock(Payum::class);
        $sessionManager = m::mock(SessionManager::class);
        $responseFactory = m::mock(ResponseFactory::class);
        $replyToSymfonyResponseConverter = m::mock(ReplyToSymfonyResponseConverter::class);
        $encrypter = m::mock(Encrypter::class);
        $payment = new Payment($payum, $sessionManager, $responseFactory, $replyToSymfonyResponseConverter, $encrypter);

        /*
        |------------------------------------------------------------
        | Expectation
        |------------------------------------------------------------
        */

        /*
        |------------------------------------------------------------
        | Assertion
        |------------------------------------------------------------
        */

        $this->assertSame($payum, $payment->getPayum());
    }

    public function testSend()
    {
        /*
        |------------------------------------------------------------
        | Set
        |------------------------------------------------------------
        */

        $payum = m::mock(Payum::class);
        $sessionManager = m::mock(SessionManager::class);
        $responseFactory = m::mock(ResponseFactory::class);
        $replyToSymfonyResponseConverter = m::mock(ReplyToSymfonyResponseConverter::class);
        $encrypter = m::mock(Encrypter::class);
        $payment = new Payment($payum, $sessionManager, $responseFactory, $replyToSymfonyResponseConverter, $encrypter);
        $request = m::mock(Request::class);
        $httpRequestVerifier = m::mock(HttpRequestVerifierInterface::class);
        $token = m::mock(TokenInterface::class);
        $payumToken = uniqid();
        $gateway = m::mock(GatewayInterface::class);
        $payumTokenId = 'payum_token';
        $gatewayName = 'fooGatewayName';

        /*
        |------------------------------------------------------------
        | Expectation
        |------------------------------------------------------------
        */

        $payum->shouldReceive('getHttpRequestVerifier')->once()->andReturn($httpRequestVerifier)
            ->shouldReceive('getGateway')->with($gatewayName)->once()->andReturn($gateway);

        $request->shouldReceive('merge')->with([
            $payumTokenId => $payumToken,
        ])->once();

        $httpRequestVerifier->shouldReceive('verify')->once()->andReturn($token);

        $token->shouldReceive('getGatewayName')->once()->andReturn($gatewayName);

        /*
        |------------------------------------------------------------
        | Assertion
        |------------------------------------------------------------
        */

        $payment->send($request, $payumToken, function () use ($gateway, $token, $httpRequestVerifier) {
            $args = func_get_args();
            $this->assertSame($args[0], $gateway);
            $this->assertSame($args[1], $token);
            $this->assertSame($args[2], $httpRequestVerifier);
        });
    }

    public function testGetTokenWithoutPayumToken()
    {
        /*
        |------------------------------------------------------------
        | Set
        |------------------------------------------------------------
        */

        $payum = m::mock(Payum::class);
        $sessionManager = m::mock(SessionManager::class);
        $responseFactory = m::mock(ResponseFactory::class);
        $replyToSymfonyResponseConverter = m::mock(ReplyToSymfonyResponseConverter::class);
        $encrypter = m::mock(Encrypter::class);
        $payment = new Payment($payum, $sessionManager, $responseFactory, $replyToSymfonyResponseConverter, $encrypter);
        $request = m::mock(Request::class);
        $request->cookies = m::mock(stdClass::class);
        $httpRequestVerifier = m::mock(HttpRequestVerifierInterface::class);
        $token = m::mock(TokenInterface::class);
        $payumToken = uniqid();
        $gateway = m::mock(GatewayInterface::class);
        $payumTokenId = 'payum_token';
        $gatewayName = 'fooGatewayName';
        $sessionName = 'foo';
        /*
        |------------------------------------------------------------
        | Expectation
        |------------------------------------------------------------
        */

        $payum->shouldReceive('getHttpRequestVerifier')->once()->andReturn($httpRequestVerifier)
            ->shouldReceive('getGateway')->with($gatewayName)->once()->andReturn($gateway);

        $sessionManager
            ->shouldReceive('driver')->once()->andReturnSelf()
            ->shouldReceive('isStarted')->once()->andReturn(false)
            ->shouldReceive('getName')->once()->andReturn($sessionName)
            ->shouldReceive('isValidId')->once()->andReturn(false)
            ->shouldReceive('setId')->with($sessionName)->once()
            ->shouldReceive('setRequestOnHandler')->with($request)->once()
            ->shouldReceive('start')->twice()
            ->shouldReceive('forget')->with($payumTokenId)->once()->andReturn(true)
            ->shouldReceive('get')->once()->andReturn($payumToken)
            ->shouldReceive('save')->once();

        $encrypter->shouldReceive('decrypt')->with($sessionName)->once()->andReturn($sessionName);

        $request->shouldReceive('merge')->with([
            $payumTokenId => $payumToken,
        ])->once();

        $request->cookies->shouldReceive('get')->with($sessionName)->andReturn($sessionName);

        $httpRequestVerifier->shouldReceive('verify')->once()->andReturn($token);

        $token->shouldReceive('getGatewayName')->once()->andReturn($gatewayName);

        /*
        |------------------------------------------------------------
        | Assertion
        |------------------------------------------------------------
        */

        $payment->send($request, null, function () use ($gateway, $token, $httpRequestVerifier) {
            $args = func_get_args();
            $this->assertSame($args[0], $gateway);
            $this->assertSame($args[1], $token);
            $this->assertSame($args[2], $httpRequestVerifier);
        });
    }

    public function testConvertReply()
    {
        /*
        |------------------------------------------------------------
        | Set
        |------------------------------------------------------------
        */

        $payum = m::mock(Payum::class);
        $sessionManager = m::mock(SessionManager::class);
        $responseFactory = m::mock(ResponseFactory::class);
        $replyToSymfonyResponseConverter = m::mock(ReplyToSymfonyResponseConverter::class);
        $encrypter = m::mock(Encrypter::class);
        $payment = new Payment($payum, $sessionManager, $responseFactory, $replyToSymfonyResponseConverter, $encrypter);
        $request = m::mock(Request::class);
        $request->cookies = m::mock(stdClass::class);
        $httpRequestVerifier = m::mock(HttpRequestVerifierInterface::class);
        $token = m::mock(TokenInterface::class);
        $payumToken = uniqid();
        $gateway = m::mock(GatewayInterface::class);
        $reply = m::mock(ReplyInterface::class);
        $payumTokenId = 'payum_token';
        $gatewayName = 'fooGatewayName';
        $sessionName = 'foo';

        /*
        |------------------------------------------------------------
        | Expectation
        |------------------------------------------------------------
        */

        $payum->shouldReceive('getHttpRequestVerifier')->once()->andReturn($httpRequestVerifier)
            ->shouldReceive('getGateway')->with($gatewayName)->once()->andReturn($gateway);

        $request->shouldReceive('merge')->with([
            $payumTokenId => $payumToken,
        ])->once();

        $request->cookies->shouldReceive('get')->with($sessionName)->andReturn($sessionName);

        $httpRequestVerifier->shouldReceive('verify')->once()->andReturn($token);

        $token->shouldReceive('getGatewayName')->once()->andReturn($gatewayName);

        $sessionManager
            ->shouldReceive('driver')->once()->andReturnSelf()
            ->shouldReceive('isStarted')->once()->andReturn(false)
            ->shouldReceive('isValidId')->once()->andReturn(false)
            ->shouldReceive('getName')->once()->andReturn($sessionName)
            ->shouldReceive('setId')->with($sessionName)->once()
            ->shouldReceive('setRequestOnHandler')->with($request)->once()
            ->shouldReceive('start')->once()
            ->shouldReceive('set')->with($payumTokenId, $payumToken)
            ->shouldReceive('save')->once();

        $encrypter->shouldReceive('decrypt')->with($sessionName)->once()->andReturn($sessionName);

        $replyToSymfonyResponseConverter->shouldReceive('convert')->with(m::type(ReplyInterface::class))->andReturn($reply);

        /*
        |------------------------------------------------------------
        | Assertion
        |------------------------------------------------------------
        */

        $this->assertSame($reply, $payment->send($request, $payumToken, function () {
            throw new HttpResponse('testing');
        }));
    }

    public function testPrepare()
    {
        /*
        |------------------------------------------------------------
        | Set
        |------------------------------------------------------------
        */

        $payum = m::mock(Payum::class);
        $sessionManager = m::mock(SessionManager::class);
        $responseFactory = m::mock(ResponseFactory::class);
        $replyToSymfonyResponseConverter = m::mock(ReplyToSymfonyResponseConverter::class);
        $encrypter = m::mock(Encrypter::class);
        $payment = new Payment($payum, $sessionManager, $responseFactory, $replyToSymfonyResponseConverter, $encrypter);
        $request = m::mock(Request::class);
        $gatewayName = 'fooGatewayName';
        $storage = m::mock(stdClass::class);
        $eloquentPayment = m::mock(EloquentPayment::class);
        $token = m::mock(TokenInterface::class);

        /*
        |------------------------------------------------------------
        | Expectation
        |------------------------------------------------------------
        */

        $excepted = 'fooTargetUrl';

        $storage->shouldReceive('create')->andReturn($eloquentPayment)
            ->shouldReceive('update')->andReturn($eloquentPayment);

        $responseFactory->shouldReceive('redirectTo')->once();

        $token->shouldReceive('getTargetUrl')->andReturn($excepted);

        $payum->shouldReceive('getStorages')->once()->andReturn([
                EloquentPayment::class => 'storage',
            ])
            ->shouldReceive('getStorage')->once()->andReturn($storage)
            ->shouldReceive('getTokenFactory->createCaptureToken')->andReturn($token);

        $payment->prepare($gatewayName, function () {
        }, 'payment.done', []);

        /*
        |------------------------------------------------------------
        | Assertion
        |------------------------------------------------------------
        */
    }

    public function testDone()
    {
        /*
        |------------------------------------------------------------
        | Set
        |------------------------------------------------------------
        */

        $payum = m::mock(Payum::class);
        $sessionManager = m::mock(SessionManager::class);
        $responseFactory = m::mock(ResponseFactory::class);
        $replyToSymfonyResponseConverter = m::mock(ReplyToSymfonyResponseConverter::class);
        $encrypter = m::mock(Encrypter::class);
        $payment = new Payment($payum, $sessionManager, $responseFactory, $replyToSymfonyResponseConverter, $encrypter);
        $request = m::mock(Request::class);
        $httpRequestVerifier = m::mock(HttpRequestVerifierInterface::class);
        $token = m::mock(TokenInterface::class);
        $payumToken = uniqid();
        $gateway = m::mock(GatewayInterface::class);
        $payumTokenId = 'payum_token';
        $gatewayName = 'fooGatewayName';

        /*
        |------------------------------------------------------------
        | Expectation
        |------------------------------------------------------------
        */

        $payum->shouldReceive('getHttpRequestVerifier')->once()->andReturn($httpRequestVerifier)
            ->shouldReceive('getGateway')->with($gatewayName)->once()->andReturn($gateway);

        $request->shouldReceive('merge')->with([
            $payumTokenId => $payumToken,
        ])->once();

        $httpRequestVerifier->shouldReceive('verify')->with($request)->once()->andReturn($token);

        $token->shouldReceive('getGatewayName')->once()->andReturn($gatewayName);

        $gateway->shouldReceive('execute')->with(m::type(GetHumanStatus::class))->once();

        /*
        |------------------------------------------------------------
        | Assertion
        |------------------------------------------------------------
        */

        $payment->done($request, $payumToken, function ($status) use ($gateway, $token, $httpRequestVerifier) {
            $args = func_get_args();
            $this->assertSame($token, $status->getModel());
            $this->assertSame($args[2], $gateway);
            $this->assertSame($args[3], $token);
            $this->assertSame($args[4], $httpRequestVerifier);
        });
    }

    public function testAuthorize()
    {
        /*
        |------------------------------------------------------------
        | Set
        |------------------------------------------------------------
        */

        $payum = m::mock(Payum::class);
        $sessionManager = m::mock(SessionManager::class);
        $responseFactory = m::mock(ResponseFactory::class);
        $replyToSymfonyResponseConverter = m::mock(ReplyToSymfonyResponseConverter::class);
        $encrypter = m::mock(Encrypter::class);
        $payment = new Payment($payum, $sessionManager, $responseFactory, $replyToSymfonyResponseConverter, $encrypter);
        $request = m::mock(Request::class);
        $httpRequestVerifier = m::mock(HttpRequestVerifierInterface::class);
        $token = m::mock(TokenInterface::class);
        $payumToken = uniqid();
        $gateway = m::mock(GatewayInterface::class);
        $payumTokenId = 'payum_token';
        $gatewayName = 'fooGatewayName';

        /*
        |------------------------------------------------------------
        | Expectation
        |------------------------------------------------------------
        */
        $excepted = 'fooTargetUrl';

        $payum->shouldReceive('getHttpRequestVerifier')->once()->andReturn($httpRequestVerifier)
            ->shouldReceive('getGateway')->with($gatewayName)->once()->andReturn($gateway);

        $request->shouldReceive('merge')->with([
            $payumTokenId => $payumToken,
        ])->once();

        $httpRequestVerifier->shouldReceive('verify')->with($request)->once()->andReturn($token)
            ->shouldReceive('invalidate')->with($token);

        $gateway->shouldReceive('execute')->with(m::type(Authorize::class));

        $responseFactory->shouldReceive('redirectTo')->once()->andReturn($excepted);

        $token->shouldReceive('getGatewayName')->once()->andReturn($gatewayName)
            ->shouldReceive('getAfterUrl')->once()->andReturn($excepted);

        /*
        |------------------------------------------------------------
        | Assertion
        |------------------------------------------------------------
        */

        $this->assertSame($excepted, $payment->authorize($request, $payumToken));
    }

    public function testCapture()
    {
        /*
        |------------------------------------------------------------
        | Set
        |------------------------------------------------------------
        */

        $payum = m::mock(Payum::class);
        $sessionManager = m::mock(SessionManager::class);
        $responseFactory = m::mock(ResponseFactory::class);
        $replyToSymfonyResponseConverter = m::mock(ReplyToSymfonyResponseConverter::class);
        $encrypter = m::mock(Encrypter::class);
        $payment = new Payment($payum, $sessionManager, $responseFactory, $replyToSymfonyResponseConverter, $encrypter);
        $request = m::mock(Request::class);
        $httpRequestVerifier = m::mock(HttpRequestVerifierInterface::class);
        $token = m::mock(TokenInterface::class);
        $payumToken = uniqid();
        $gateway = m::mock(GatewayInterface::class);
        $payumTokenId = 'payum_token';
        $gatewayName = 'fooGatewayName';

        /*
        |------------------------------------------------------------
        | Expectation
        |------------------------------------------------------------
        */
        $excepted = 'fooTargetUrl';

        $payum->shouldReceive('getHttpRequestVerifier')->once()->andReturn($httpRequestVerifier)
            ->shouldReceive('getGateway')->with($gatewayName)->once()->andReturn($gateway);

        $request->shouldReceive('merge')->with([
            $payumTokenId => $payumToken,
        ])->once();

        $httpRequestVerifier->shouldReceive('verify')->with($request)->once()->andReturn($token)
            ->shouldReceive('invalidate')->with($token);

        $gateway->shouldReceive('execute')->with(m::type(Capture::class));

        $responseFactory->shouldReceive('redirectTo')->once()->andReturn($excepted);

        $token->shouldReceive('getGatewayName')->once()->andReturn($gatewayName)
            ->shouldReceive('getAfterUrl')->once()->andReturn($excepted);

        /*
        |------------------------------------------------------------
        | Assertion
        |------------------------------------------------------------
        */

        $this->assertSame($excepted, $payment->capture($request, $payumToken));
    }

    public function testNotify()
    {
        /*
        |------------------------------------------------------------
        | Set
        |------------------------------------------------------------
        */

        $payum = m::mock(Payum::class);
        $sessionManager = m::mock(SessionManager::class);
        $responseFactory = m::mock(ResponseFactory::class);
        $replyToSymfonyResponseConverter = m::mock(ReplyToSymfonyResponseConverter::class);
        $encrypter = m::mock(Encrypter::class);
        $payment = new Payment($payum, $sessionManager, $responseFactory, $replyToSymfonyResponseConverter, $encrypter);
        $request = m::mock(Request::class);
        $httpRequestVerifier = m::mock(HttpRequestVerifierInterface::class);
        $token = m::mock(TokenInterface::class);
        $payumToken = uniqid();
        $gateway = m::mock(GatewayInterface::class);
        $payumTokenId = 'payum_token';
        $gatewayName = 'fooGatewayName';

        /*
        |------------------------------------------------------------
        | Expectation
        |------------------------------------------------------------
        */
        $excepted = 'fooTargetUrl';

        $payum->shouldReceive('getHttpRequestVerifier')->once()->andReturn($httpRequestVerifier)
            ->shouldReceive('getGateway')->with($gatewayName)->once()->andReturn($gateway);

        $request->shouldReceive('merge')->with([
            $payumTokenId => $payumToken,
        ])->once();

        $httpRequestVerifier->shouldReceive('verify')->with($request)->once()->andReturn($token)
            ->shouldReceive('invalidate')->with($token);

        $gateway->shouldReceive('execute')->with(m::type(Notify::class));

        $token->shouldReceive('getGatewayName')->once()->andReturn($gatewayName);

        $responseFactory->shouldReceive('make')->once()->andReturn(204);

        /*
        |------------------------------------------------------------
        | Assertion
        |------------------------------------------------------------
        */

        $response = $payment->notify($request, $payumToken);
        $this->assertNull($response[0]);
        $this->assertSame(204, $response);
    }

    public function testNotifyUnsafe()
    {
        /*
        |------------------------------------------------------------
        | Set
        |------------------------------------------------------------
        */

        $payum = m::mock(Payum::class);
        $sessionManager = m::mock(SessionManager::class);
        $responseFactory = m::mock(ResponseFactory::class);
        $replyToSymfonyResponseConverter = m::mock(ReplyToSymfonyResponseConverter::class);
        $encrypter = m::mock(Encrypter::class);
        $payment = new Payment($payum, $sessionManager, $responseFactory, $replyToSymfonyResponseConverter, $encrypter);
        $token = m::mock(TokenInterface::class);
        $payumToken = uniqid();
        $gateway = m::mock(GatewayInterface::class);
        $payumTokenId = 'payum_token';
        $gatewayName = 'fooGatewayName';

        /*
        |------------------------------------------------------------
        | Expectation
        |------------------------------------------------------------
        */
        $payum->shouldReceive('getGateway')->with($gatewayName)->once()->andReturn($gateway);

        $gateway->shouldReceive('execute')->with(m::type(Notify::class));

        $responseFactory->shouldReceive('make')->once()->andReturn(204);

        /*
        |------------------------------------------------------------
        | Assertion
        |------------------------------------------------------------
        */

        $response = $payment->notifyUnsafe($gatewayName);
        $this->assertSame(204, $response);
    }

    public function testPayout()
    {
        /*
        |------------------------------------------------------------
        | Set
        |------------------------------------------------------------
        */

        $payum = m::mock(Payum::class);
        $sessionManager = m::mock(SessionManager::class);
        $responseFactory = m::mock(ResponseFactory::class);
        $replyToSymfonyResponseConverter = m::mock(ReplyToSymfonyResponseConverter::class);
        $encrypter = m::mock(Encrypter::class);
        $payment = new Payment($payum, $sessionManager, $responseFactory, $replyToSymfonyResponseConverter, $encrypter);
        $request = m::mock(Request::class);
        $httpRequestVerifier = m::mock(HttpRequestVerifierInterface::class);
        $token = m::mock(TokenInterface::class);
        $payumToken = uniqid();
        $gateway = m::mock(GatewayInterface::class);
        $payumTokenId = 'payum_token';
        $gatewayName = 'fooGatewayName';

        /*
        |------------------------------------------------------------
        | Expectation
        |------------------------------------------------------------
        */
        $excepted = 'fooTargetUrl';

        $payum->shouldReceive('getHttpRequestVerifier')->once()->andReturn($httpRequestVerifier)
            ->shouldReceive('getGateway')->with($gatewayName)->once()->andReturn($gateway);

        $request->shouldReceive('merge')->with([
            $payumTokenId => $payumToken,
        ])->once();

        $httpRequestVerifier->shouldReceive('verify')->with($request)->once()->andReturn($token)
            ->shouldReceive('invalidate')->with($token);

        $gateway->shouldReceive('execute')->with(m::type(Payout::class));

        $responseFactory->shouldReceive('redirectTo')->once()->andReturn($excepted);

        $token->shouldReceive('getGatewayName')->once()->andReturn($gatewayName)
            ->shouldReceive('getAfterUrl')->once()->andReturn($excepted);

        /*
        |------------------------------------------------------------
        | Assertion
        |------------------------------------------------------------
        */

        $this->assertSame($excepted, $payment->payout($request, $payumToken));
    }

    public function testRefund()
    {
        /*
        |------------------------------------------------------------
        | Set
        |------------------------------------------------------------
        */

        $payum = m::mock(Payum::class);
        $sessionManager = m::mock(SessionManager::class);
        $responseFactory = m::mock(ResponseFactory::class);
        $replyToSymfonyResponseConverter = m::mock(ReplyToSymfonyResponseConverter::class);
        $encrypter = m::mock(Encrypter::class);
        $payment = new Payment($payum, $sessionManager, $responseFactory, $replyToSymfonyResponseConverter, $encrypter);
        $request = m::mock(Request::class);
        $httpRequestVerifier = m::mock(HttpRequestVerifierInterface::class);
        $token = m::mock(TokenInterface::class);
        $payumToken = uniqid();
        $gateway = m::mock(GatewayInterface::class);
        $payumTokenId = 'payum_token';
        $gatewayName = 'fooGatewayName';

        /*
        |------------------------------------------------------------
        | Expectation
        |------------------------------------------------------------
        */
        $excepted = 'fooTargetUrl';

        $payum->shouldReceive('getHttpRequestVerifier')->once()->andReturn($httpRequestVerifier)
            ->shouldReceive('getGateway')->with($gatewayName)->once()->andReturn($gateway);

        $request->shouldReceive('merge')->with([
            $payumTokenId => $payumToken,
        ])->once();

        $httpRequestVerifier->shouldReceive('verify')->with($request)->once()->andReturn($token)
            ->shouldReceive('invalidate')->with($token);

        $gateway->shouldReceive('execute')->with(m::type(Refund::class));

        $responseFactory->shouldReceive('redirectTo')->once()->andReturn($excepted);

        $token->shouldReceive('getGatewayName')->once()->andReturn($gatewayName)
            ->shouldReceive('getAfterUrl')->once()->andReturn($excepted);

        /*
        |------------------------------------------------------------
        | Assertion
        |------------------------------------------------------------
        */

        $this->assertSame($excepted, $payment->refund($request, $payumToken));
    }

    public function testSync()
    {
        /*
        |------------------------------------------------------------
        | Set
        |------------------------------------------------------------
        */

        $payum = m::mock(Payum::class);
        $sessionManager = m::mock(SessionManager::class);
        $responseFactory = m::mock(ResponseFactory::class);
        $replyToSymfonyResponseConverter = m::mock(ReplyToSymfonyResponseConverter::class);
        $encrypter = m::mock(Encrypter::class);
        $payment = new Payment($payum, $sessionManager, $responseFactory, $replyToSymfonyResponseConverter, $encrypter);
        $request = m::mock(Request::class);
        $httpRequestVerifier = m::mock(HttpRequestVerifierInterface::class);
        $token = m::mock(TokenInterface::class);
        $payumToken = uniqid();
        $gateway = m::mock(GatewayInterface::class);
        $payumTokenId = 'payum_token';
        $gatewayName = 'fooGatewayName';

        /*
        |------------------------------------------------------------
        | Expectation
        |------------------------------------------------------------
        */
        $excepted = 'fooTargetUrl';

        $payum->shouldReceive('getHttpRequestVerifier')->once()->andReturn($httpRequestVerifier)
            ->shouldReceive('getGateway')->with($gatewayName)->once()->andReturn($gateway);

        $request->shouldReceive('merge')->with([
            $payumTokenId => $payumToken,
        ])->once();

        $httpRequestVerifier->shouldReceive('verify')->with($request)->once()->andReturn($token)
            ->shouldReceive('invalidate')->with($token);

        $gateway->shouldReceive('execute')->with(m::type(Sync::class));

        $responseFactory->shouldReceive('redirectTo')->once()->andReturn($excepted);

        $token->shouldReceive('getGatewayName')->once()->andReturn($gatewayName)
            ->shouldReceive('getAfterUrl')->once()->andReturn($excepted);

        /*
        |------------------------------------------------------------
        | Assertion
        |------------------------------------------------------------
        */

        $this->assertSame($excepted, $payment->sync($request, $payumToken));
    }
}
