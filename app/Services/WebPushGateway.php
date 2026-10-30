<?php

declare(strict_types=1);
namespace App\Services;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Minishlink\WebPush\{WebPush,Subscription};
use Psr\Http\Client\ClientInterface;

final class WebPushGateway implements PushGateway
{
    private WebPush $client;
    public function __construct(array $config, ?ClientInterface $http=null)
    {
        if (!self::configured($config)) throw new \RuntimeException('Push indisponível.');
        $factory=new HttpFactory();
        $this->client=new WebPush(['VAPID'=>['subject'=>$config['subject'],'publicKey'=>$config['public_key'],'privateKey'=>$config['private_key']]],['TTL'=>300,'urgency'=>'normal'], $http??new Client(['timeout'=>10,'connect_timeout'=>5,'allow_redirects'=>false]),$factory,$factory,logger:new \Psr\Log\NullLogger());
    }
    public static function configured(array $config): bool
    {
        return !empty($config['public_key'])&&!empty($config['private_key'])&&!empty($config['subject']);
    }
    public function send(array $subscription,array $payload): int
    {
        $report=$this->client->sendOneNotification(new Subscription($subscription['endpoint'],$subscription['public_key'],$subscription['auth_token'],$subscription['content_encoding']),json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
        return $report->isSuccess()?201:($report->getResponse()?->getStatusCode()??503);
    }
}
