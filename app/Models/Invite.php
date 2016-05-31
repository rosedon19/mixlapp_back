<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invite extends Model
{
    /**
	 * The database table used by the model.
	 *
	 * @var string
	 */
	protected $table = 'invites';
	public $timestamps = false;
	protected $primaryKey = 'id';
    const STATUS_NEW = 0;
    const STATUS_ACCEPTED = 1;
    const STATUS_DENIED = 2;
    const STATUS_CLAIMED = 3;
    const STATUS_EXPIRED = 4;
    const STATUS_CLOSED = 99;
    
    public static function generateVoucher() {
        $length = 5; //Voucher number length;
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }
}
