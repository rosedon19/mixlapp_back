<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IoToken extends Model  {

	/**
	 * The database table used by the model.
	 *
	 * @var string
	 */
	protected $table = 'io_tokens';
    
    public static function getUserToken($userId) {
        $db_token = IoToken::where('user_id', $userId)->get();
        $tokens = [];
        if($db_token) {
            foreach($db_token as $dt) {
                $tokens []= $dt->token;
            }
        }
        return empty($tokens) ? false : $tokens;
    }
    
    public static function updateToken($user_id, $token){
        if(isset($token) && !empty($token)) {
            $db_token = IoToken::where('user_id', $user_id)
                ->where('token', $token)->first();
            if(!$db_token){
                IoToken::insert( array( 'user_id' => $user_id, 'token' => $token, ) );
            }
        } else {
            return false;
        }
        return true;
    }
}

