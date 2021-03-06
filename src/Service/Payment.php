<?php

namespace Recca0120\LaravelPayum\Service;

use Closure;
use Illuminate\Contracts\Encryption\Encrypter as EncrypterContract;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Request;
use Illuminate\Session\SessionManager;
use Payum\Core\Bridge\Symfony\ReplyToSymfonyResponseConverter;
use Payum\Core\Model\Payment as PayumPayment;
use Payum\Core\Payum;
use Payum\Core\Reply\ReplyInterface;
use Payum\Core\Request\Authorize;
use Payum\Core\Request\Capture;
use Payum\Core\Request\GetHumanStatus;
use Payum\Core\Request\Notify;
use Payum\Core\Request\Payout;
use Payum\Core\Request\Refund;
use Payum\Core\Request\Sync;
use Payum\Core\Security\HttpRequestVerifierInterface;
use Recca0120\LaravelPayum\Model\Payment as EloquentPayment;

class Payment
{
    /**
     * $payum.
     *
     * @var \Payum\Core\Payum
     */
    protected $payum;

    /**
     * $sessionManager.
     *
     * @var \Illuminate\Session\SessionManager
     */
    protected $sessionManager;

    /**
     * $responseFactory.
     *
     * @var \Illuminate\Contracts\Routing\ResponseFactory
     */
    protected $responseFactory;

    /**
     * $converter.
     *
     * @var \Payum\Core\Bridge\Symfony\ReplyToSymfonyResponseConverter
     */
    protected $converter;

    /**
     * $encrypter.
     *
     * @var \Illuminate\Contracts\Encryption\Encrypter
     */
    protected $encrypter;

    /**
     * $payumTokenId.
     *
     * @var string
     */
    protected $payumTokenId = 'payum_token';

    /**
     * __construct.
     *
     * @method __construct
     *
     * @param \Payum\Core\Payum                                          $payum
     * @param \Illuminate\Session\SessionManager                         $sessionManager
     * @param \Illuminate\Contracts\Routing\ResponseFactory              $responseFactory
     * @param \Payum\Core\Bridge\Symfony\ReplyToSymfonyResponseConverter $converter
     * @param \Illuminate\Contracts\Encryption\Encrypter                 $encrypter
     */
    public function __construct(
        Payum $payum,
        SessionManager $sessionManager,
        ResponseFactory $responseFactory,
        ReplyToSymfonyResponseConverter $converter,
        EncrypterContract $encrypter
    ) {
        $this->payum = $payum;
        $this->sessionManager = $sessionManager;
        $this->responseFactory = $responseFactory;
        $this->converter = $converter;
        $this->encrypter = $encrypter;
    }

    /**
     * getPayum.
     *
     * @method getPayum
     *
     * @return \Payum\Core\Payum
     */
    public function getPayum()
    {
        return $this->payum;
    }

    /**
     * getSession.
     *
     * @method getSession
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Session\Store
     */
    protected function getSession($request)
    {
        $session = $this->sessionManager->driver();
        if ($session->isStarted() === false) {
            $sessionName = $request->cookies->get($session->getName());
            $sessionName = ($session->isValidId($sessionName) === false) ? $this->encrypter->decrypt($sessionName) : $sessionName;
            $session->setId($sessionName);
            $session->setRequestOnHandler($request);
            $session->start();
        }

        return $session;
    }

    /**
     * getToken.
     *
     * @method getToken
     *
     * @param \Payum\Core\Security\HttpRequestVerifierInterface $httpRequestVerifier
     * @param \Illuminate\Http\Request                          $request
     * @param string                                            $payumToken
     *
     * @return \Payum\Core\Model\Token
     */
    protected function getToken(HttpRequestVerifierInterface $httpRequestVerifier, Request $request, $payumToken = null)
    {
        if (empty($payumToken) === true) {
            $session = $this->getSession($request);
            $payumToken = $session->get($this->payumTokenId);
            $session->forget($this->payumTokenId);
            $session->save();
            $session->start();
        }
        $request->merge([$this->payumTokenId => $payumToken]);

        return $httpRequestVerifier->verify($request);
    }

    /**
     * send.
     *
     * @method send
     *
     * @param \Illuminate\Http\Request $request
     * @param string                   $payumToken
     * @param \Closure                 $closure
     *
     * @return mixed
     */
    public function send(Request $request, $payumToken, Closure $closure)
    {
        $payum = $this->getPayum();
        $httpRequestVerifier = $payum->getHttpRequestVerifier();
        $token = $this->getToken($httpRequestVerifier, $request, $payumToken);
        $gateway = $payum->getGateway($token->getGatewayName());
        try {
            return $closure($gateway, $token, $httpRequestVerifier);
        } catch (ReplyInterface $reply) {
            $session = $this->getSession($request);
            $session->set('payum_token', $payumToken);
            $session->save();

            return $this->converter->convert($reply);
        }
    }

    /**
     * getPaymentModelName.
     *
     * @method getPaymentModelName
     *
     * @return string
     */
    protected function getPaymentModelName($payum)
    {
        return (in_array(EloquentPayment::class, array_keys($payum->getStorages())) === true) ?
            EloquentPayment::class : PayumPayment::class;
    }

    /**
     * create.
     *
     * @method create
     *
     * @param string   $gatewayName
     * @param \Closure $closure
     * @param string   $afterPath
     * @param array    $afterParameters
     *
     * @return mixed
     */
    public function prepare($gatewayName, Closure $closure, $afterPath = 'payment.done', array $afterParameters = [])
    {
        $payum = $this->getPayum();
        $storage = $payum->getStorage($this->getPaymentModelName($payum));
        $payment = $storage->create();
        $closure($payment, $gatewayName, $storage, $this->payum);
        $storage->update($payment);
        $captureToken = $this->payum
            ->getTokenFactory()
            ->createCaptureToken($gatewayName, $payment, $afterPath, $afterParameters);

        return $this->responseFactory->redirectTo($captureToken->getTargetUrl());
    }

    /**
     * done.
     *
     * @method done
     *
     * @param \Illuminate\Http\Request $request
     * @param string                   $payumToken
     * @param \Closure                 $closure
     *
     * @return mixed
     */
    public function done(Request $request, $payumToken, Closure $closure)
    {
        return $this->send($request, $payumToken, function ($gateway, $token, $httpRequestVerifier) use ($closure) {
            $gateway->execute($status = new GetHumanStatus($token));
            $payment = $status->getFirstModel();

            return $closure($status, $payment, $gateway, $token, $httpRequestVerifier);
        });
    }

    /**
     * authorize.
     *
     * @method authorize
     *
     * @param \Illuminate\Http\Request $request
     * @param string                   $payumToken
     *
     * @return mixed
     */
    public function authorize(Request $request, $payumToken)
    {
        return $this->send($request, $payumToken, function ($gateway, $token, $httpRequestVerifier) {
            $gateway->execute(new Authorize($token));
            $httpRequestVerifier->invalidate($token);

            return $this->responseFactory->redirectTo($token->getAfterUrl());
        });
    }

    /**
     * capture.
     *
     * @method capture
     *
     * @param \Illuminate\Http\Request $request
     * @param string                   $payumToken
     *
     * @return mixed
     */
    public function capture(Request $request, $payumToken = null)
    {
        return $this->send($request, $payumToken, function ($gateway, $token, $httpRequestVerifier) {
            $gateway->execute(new Capture($token));
            $httpRequestVerifier->invalidate($token);

            return $this->responseFactory->redirectTo($token->getAfterUrl());
        });
    }

    /**
     * notify.
     *
     * @method notify
     *
     * @param \Illuminate\Http\Request $request
     * @param string                   $payumToken
     *
     * @return mixed
     */
    public function notify(Request $request, $payumToken)
    {
        return $this->send($request, $payumToken, function ($gateway, $token, $httpRequestVerifier) {
            $gateway->execute(new Notify($token));

            return $this->responseFactory->make(null, 204);
        });
    }

    /**
     * notifyUnsafe.
     *
     * @method notifyUnsafe
     *
     * @param string $gatewayName
     *
     * @return mixed
     */
    public function notifyUnsafe($gatewayName)
    {
        $gateway = $this->getPayum()->getGateway($gatewayName);
        $gateway->execute(new Notify(null));

        return $this->responseFactory->make(null, 204);
    }

    /**
     * payout.
     *
     * @method payout
     *
     * @param \Illuminate\Http\Request $request
     * @param string                   $payumToken
     *
     * @return mixed
     */
    public function payout(Request $request, $payumToken)
    {
        return $this->send($request, $payumToken, function ($gateway, $token, $httpRequestVerifier) {
            $gateway->execute(new Payout($token));
            $httpRequestVerifier->invalidate($token);

            return $this->responseFactory->redirectTo($token->getAfterUrl());
        });
    }

    /**
     * refund.
     *
     * @method refund
     *
     * @param \Illuminate\Http\Request $request
     * @param string                   $payumToken
     *
     * @return mixed
     */
    public function refund(Request $request, $payumToken)
    {
        return $this->send($request, $payumToken, function ($gateway, $token, $httpRequestVerifier) {
            $gateway->execute(new Refund($token));
            $httpRequestVerifier->invalidate($token);
            $afterUrl = $token->getAfterUrl();
            if (empty($afterUrl) === false) {
                return $this->responseFactory->redirectTo($afterUrl);
            }

            return $this->responseFactory->make(null, 204);
        });
    }

    /**
     * sync.
     *
     * @method sync
     *
     * @param \Illuminate\Http\Request $request
     * @param string                   $payumToken
     *
     * @return mixed
     */
    public function sync(Request $request, $payumToken)
    {
        return $this->send($request, $payumToken, function ($gateway, $token, $httpRequestVerifier) {
            $gateway->execute(new Sync($token));
            $httpRequestVerifier->invalidate($token);

            return $this->responseFactory->redirectTo($token->getAfterUrl());
        });
    }
}
