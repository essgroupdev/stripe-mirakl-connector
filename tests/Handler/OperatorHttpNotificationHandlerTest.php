<?php

namespace App\Tests\MessageHandler;

use App\Handler\OperatorHttpNotificationHandler;
use App\Message\AccountUpdateMessage;
use App\Message\PayoutFailedMessage;
use App\Message\TransferFailedMessage;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class OperatorHttpNotificationHandlerTest extends TestCase
{
    /**
     * @var OperatorHttpNotificationHandler
     */
    private $handler;

    /**
     * @var HttpClient
     */
    private $client;

    /**
     * @var ResponseInterface
     */
    private $response;

    protected function setUp(): void
    {
        $this->operatorNotificationUrl = 'https://operator.com/notification';

        $this->client = $this->createMock(HttpClientInterface::class);

        $this->response = $this->createMock(ResponseInterface::class);

        $this->handler = new OperatorHttpNotificationHandler(
            $this->client,
            $this->operatorNotificationUrl
        );

        $logger = new NullLogger();

        $this->handler->setLogger($logger);
    }

    public function testEmptyUrlOperatorHttpNotificationHandler()
    {
        $this->handler = new OperatorHttpNotificationHandler(
            $this->client,
            ''
        );

        $this
            ->response
            ->expects($this->never())
            ->method('getStatusCode');

        $this
            ->client
            ->expects($this->never())
            ->method('request');

        $handler = $this->handler;
        $handler(new AccountUpdateMessage(1, '12345'));
    }

    public function testOperatorHttpNotificationHandler()
    {
        $miraklShopId = 1;
        $stripeUserId = '12345';
        $message = new AccountUpdateMessage($miraklShopId, $stripeUserId);

        $this
            ->response
            ->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(200);

        $this
            ->client
            ->expects($this->once())
            ->method('request')
            ->willReturn($this->response);

        $handler = $this->handler;
        $handler($message);
    }

    public function testAccountUpdateHandlerError()
    {
        $miraklShopId = 1;
        $stripeUserId = '12345';
        $message = new AccountUpdateMessage($miraklShopId, $stripeUserId);

        $this
            ->response
            ->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(400);

        $this->response
            ->expects($this->at(0))
            ->method('getInfo')
            ->with('http_code')
            ->willReturn(400);
        $this->response
            ->expects($this->at(1))
            ->method('getInfo')
            ->with('url')
            ->willReturn('http://badrequest');
        $this->response
            ->expects($this->at(2))
            ->method('getInfo')
            ->with('response_headers')
            ->willReturn([]);

        $this->response
            ->method('getContent')
            ->willThrowException(new ClientException($this->response));

        $this
            ->client
            ->expects($this->once())
            ->method('request')
            ->willReturn($this->response);

        $this->expectException(ClientException::class);

        $handler = $this->handler;
        $handler($message);
    }

    public function testGetHandledMessage()
    {
        $handledMessage = iterator_to_array(OperatorHttpNotificationHandler::getHandledMessages());
        $this->assertEquals([
            TransferFailedMessage::class,
            PayoutFailedMessage::class,
            AccountUpdateMessage::class => [
                'from_transport' => 'operator_http_notification',
            ],
        ], $handledMessage);
    }
}
