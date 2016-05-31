<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    /**
	 * The database table used by the model.
	 *
	 * @var string
	 */
	protected $table = 'notifications';
	public $timestamps = false;
	protected $primaryKey = 'id';
	
	const NOTIFICATION_CHANNEL_APN = 1;
	const NOTIFICATION_CHANNEL_GCM = 2;
	const NOTIFICATION_CHANNEL_MAIL = 3;
	
	/**
	 * @desc send push sendNotification
	 * @param $channel integer NOTIFICATION_CHANNEL_APN|NOTIFICATION_CHANNEL_GCM|NOTIFICATION_CHANNEL_MAIL
	 * @param $senderUserId
	 * @param $receiverUserId
	 * @param $receiverToken
	 * @param $content mixed { "title": string, "body": string, "custom" : any }
	 */
	public static function sendNotification($senderUserId, $receiverUserId, $receiverToken, $content) {
		$channel = Notification::NOTIFICATION_CHANNEL_APN;
		if(empty($receiverToken)) {
			return false;
		}
		//for now we have just iOS APN push
		if($channel == Notification::NOTIFICATION_CHANNEL_APN) {
			$result = Notification::_pushThroughApn($receiverToken, $content);
		} elseif($channel == Notification::NOTIFICATION_CHANNEL_GCM) {
			$result = Notification::_pushThroughGcm($receiverToken, $content);
		} elseif($channel == Notification::NOTIFICATION_CHANNEL_MAIL) {
			$result = Notification::_sendMail($receiverToken, $content);
		}
		//saving into history record
		if($result!==false) {
			if(!empty($receiverToken)) {
			if(!is_array($receiverToken)) {
				$receiverToken = array($receiverToken);
			}
			foreach($receiverToken as $rt) {
				$notify = new Notification;
				$notify->sender_user_id = $senderUserId;
				$notify->receiver_user_id = $receiverUserId;
				$notify->receiver_token = $rt;
				$notify->content = $result;
				$notify->sent_at = date('Y-m-d H:i:s');
				$notify->save();
			}
		}
		}
		return $result;
	}
	
	/**
	 * @desc send push notification through Apple Push Network(APN)
	 * @param $receiverToken single or array of device tokens
	 * @param $content object { 'title' : string, 'body' : string, 'launch-image' : string, 'custom' : any }
	 * @return string payload sent, boolean false when fail
	 */
	protected static function _pushThroughApn($receiverToken, $content) {
		$passphrase = 'abc123456'; // Put your private key's passphrase here:
		$certPath = 'PushMixl.pem'; //Path to your private key file 
		$apnUrl = 'ssl://gateway.sandbox.push.apple.com:2195'; //sandbox
		//$apnUrl = 'ssl://gateway.push.apple.com:2195'; //production
		////
        $deviceToken = $receiverToken; // Put your device token here (without spaces):
        $message = $content; // Put your alert message here:
        ////
        $ctx = stream_context_create();
        stream_context_set_option($ctx, 'ssl', 'local_cert', $certPath);
        stream_context_set_option($ctx, 'ssl', 'passphrase', $passphrase);
        // Open a connection to the APNS server
        $fp = stream_socket_client( $apnUrl, $err, $errstr, 60, STREAM_CLIENT_CONNECT|STREAM_CLIENT_PERSISTENT, $ctx);
        if (!$fp) {
            return false;
		}
        // Create the payload body
        $body['aps'] = array(
			'alert'=> array(
				'title' => $content['title'],
				'body' => $content['body'],
			),
			'badge'=>1,
			'sound'=>'default',
			'notify'=>'notification'
		);
		$body['info'] = $content['custom']; //custom payload
        // Encode the payload as JSON
        $payload = json_encode($body);

        if(!is_array($deviceToken)) {
            $deviceToken = array($deviceToken);
        }
        foreach($deviceToken as $token){
            // Build the binary notification
            $msg = chr(0) . pack('n', 32) . @pack('H*', str_replace(' ', '', $token)) . @pack('n', strlen($payload)) . $payload;
            // Send it to the server
            $result = fwrite($fp, $msg, strlen($msg));
        }
        fclose($fp);
		return $payload;
	}
	
	/**
	 * @desc send push notification through Google Cloud Messaging(GCM)
	 * @param $regId single or array of Registeration IDs for the receiving devices
	 * @param $content any
	 * @return string json encoded $content when success, boolean false when fail
	 */
	protected static function _pushThroughGcm($regId, $content) {
		$gcmApiAccessKey = ''; //Your GCM API Access Key here
		if(!is_array($regId)) {
            $regId = array($regId);
        }
		// prep the bundle
		$headers = array (
            'Authorization: key=' . $gcmApiAccessKey,
            'Content-Type: application/json'
        );
        $fields = array (
            'registration_ids'     => $regId,
            'data'            => $content
        );
        // send
        $ch = curl_init();
        curl_setopt( $ch,CURLOPT_URL, 'https://android.googleapis.com/gcm/send' );
        curl_setopt( $ch,CURLOPT_POST, true );
        curl_setopt( $ch,CURLOPT_HTTPHEADER, $headers );
        curl_setopt( $ch,CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch,CURLOPT_SSL_VERIFYPEER, false );
        curl_setopt( $ch,CURLOPT_POSTFIELDS, json_encode( $fields ) );
        $result = curl_exec($ch );
        curl_close( $ch );
        return json_encode( $content );
	}
	
	/**
	 * @desc send push notification through mail
	 * @param $receiverEmail single email
	 * @param $content string
	 * @return string $content
	 */
	protected static function _sendMail($receiverEmail, $content) {
		$to      = $receiverEmail;
        $subject = "Mixl";
        $message = $content;
        $headers = 'From: no_reply@mixl.com\r\n' .
            'X-Mailer: PHP/' . phpversion();
        @mail($to, $subject, $message, $headers);
        return $content;
	}
}