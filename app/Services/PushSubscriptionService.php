<?php

declare(strict_types=1);
namespace App\Services;
use PDO;
use Throwable;

final class PushSubscriptionService
{
    public function __construct(private PDO $pdo) {}
    private function endpoint(mixed $endpoint): string
    {
        if (!is_string($endpoint)||strlen($endpoint)>2048||preg_match('/[\x00-\x20]/',$endpoint)) throw new \DomainException('Assinatura inválida.',422);
        $url=parse_url($endpoint);$host=strtolower($url['host']??'');
        $trusted=$host==='fcm.googleapis.com'||$host==='web.push.apple.com'||str_ends_with($host,'.push.apple.com')||$host==='updates.push.services.mozilla.com'||str_ends_with($host,'.push.services.mozilla.com')||str_ends_with($host,'.notify.windows.com');
        if (!$trusted||($url['scheme']??'')!=='https'||isset($url['user'])||isset($url['pass'])||isset($url['fragment'])||(isset($url['port'])&&$url['port']!==443)) throw new \DomainException('Serviço de push não reconhecido.',422);
        return $endpoint;
    }
    private function key(mixed $value,int $bytes): string
    {
        if (!is_string($value)||!preg_match('/^[A-Za-z0-9_-]+$/D',$value)) throw new \DomainException('Chave inválida.',422);
        $decoded=base64_decode(strtr($value,'-_','+/'),true);
        if ($decoded===false||strlen($decoded)!==$bytes||rtrim(strtr(base64_encode($decoded),'+/','-_'),'=')!==$value) throw new \DomainException('Chave inválida.',422);
        if ($bytes===65) {
            $der=hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200').$decoded;
            if ($decoded[0]!=="\x04"||openssl_pkey_get_public("-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($der),64,"\n")."-----END PUBLIC KEY-----\n")===false) throw new \DomainException('Chave inválida.',422);
        }
        return $value;
    }
    public function save(int $user,int $version,array $data): void
    {
        $endpoint=$this->endpoint($data['endpoint']??null);$public=$this->key($data['keys']['p256dh']??null,65);$auth=$this->key($data['keys']['auth']??null,16);$hash=hash('sha256',$endpoint);
        $this->pdo->beginTransaction();
        try {
            $q=$this->pdo->prepare('SELECT session_version,email_verified_at FROM users WHERE id=? FOR UPDATE');$q->execute([$user]);$account=$q->fetch();
            if (!$account||!$account['email_verified_at']||(int)$account['session_version']!==$version) throw new \DomainException('Entre novamente.',401);
            $q=$this->pdo->prepare('SELECT user_id FROM push_subscriptions WHERE endpoint_hash=? FOR UPDATE');$q->execute([$hash]);$owner=$q->fetchColumn();
            if ($owner!==false&&(int)$owner!==$user) throw new \DomainException('Reative o push neste navegador para esta conta.',409);
            $q=$this->pdo->prepare('SELECT COUNT(*) FROM push_subscriptions WHERE user_id=?');$q->execute([$user]);
            if ($owner===false&&(int)$q->fetchColumn()>=10) throw new \DomainException('Limite de dez dispositivos atingido.',422);
            $this->pdo->prepare("INSERT INTO push_subscriptions(user_id,endpoint,endpoint_hash,public_key,auth_token,content_encoding) VALUES (?,?,?,?,?,'aes128gcm') ON DUPLICATE KEY UPDATE public_key=VALUES(public_key),auth_token=VALUES(auth_token),updated_at=UTC_TIMESTAMP()")->execute([$user,$endpoint,$hash,$public,$auth]);
            $this->pdo->prepare('INSERT INTO notification_preferences(user_id,push_enabled) VALUES (?,1) ON DUPLICATE KEY UPDATE push_enabled=1')->execute([$user]);
            $this->pdo->commit();
        } catch(Throwable $error) {if($this->pdo->inTransaction())$this->pdo->rollBack();throw $error;}
    }
    public function remove(int $user,mixed $endpoint): void
    {
        $endpoint=$this->endpoint($endpoint);
        $this->pdo->prepare('DELETE FROM push_subscriptions WHERE user_id=? AND endpoint_hash=?')->execute([$user,hash('sha256',$endpoint)]);
    }
}
