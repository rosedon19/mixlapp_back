<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    /**
	 * The database table used by the model.
	 *
	 * @var string
	 */
	protected $table = 'contacts';
	public $timestamps = false;
	protected $primaryKey = 'id';
	
	public static function getFullName($userId, $useLastNameInitial=false) {
		$contact = Contact::where('user_id', $userId)->first();
		if($contact) {
			if($useLastNameInitial === true) {
				return $contact['firstname'].' '.substr($contact['lastname'],0,1).'.';
			} else {
				return $contact['firstname'].' '.$contact['lastname'];
			}
		}
		return '';
	}
}
