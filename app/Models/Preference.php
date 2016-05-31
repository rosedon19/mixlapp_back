<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Preference extends Model
{
    /**
	 * The database table used by the model.
	 *
	 * @var string
	 */
	protected $table = 'preferences';
	public $timestamps = false;
	protected $primaryKey = 'id';
	
	//For contact type users only
	public function isAcceptingInvites() {
		return $this->accept_invites == 1;
	}
	
	//For contact type users only
	public function isAcceptingFriends() {
		return $this->accept_friend_request == 1;
	}
	
	//For venue type users only
	public function isAcceptingMessages() {
		return $this->accept_messages == 1;
	}
	//For venue type users only
	public function isPageVisible() {
		return $this->page_visibility == 1;
	}
}
