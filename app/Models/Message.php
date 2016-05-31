<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    /**
	 * The database table used by the model.
	 *
	 * @var string
	 */
	protected $table = 'messages';
	public $timestamps = false;
	protected $primaryKey = 'id';
	
	const MESSAGE_CONTENT_TEXT = 't';
	const MESSAGE_CONTENT_IMAGE = 'i';
	
	public function markAsRead() {
		$this->read_at = Date('Y-m-d H:i:s');
		$this->is_read = 1;
		$this->save();
	}
	
	public static function putMessage($senderUserId, $receiverUserId, $content, $text) {
		$message = new Message;
		$message->sender = $senderUserId;
		$message->receiver = $receiverUserId;
		$message->content = $content;
		$message->text = $text;
		$message->sent_at = Date('Y-m-d H:i:s');
		$message->is_read = 0;
		$message->save();
		return $message;
	}
}
