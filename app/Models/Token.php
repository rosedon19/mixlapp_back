<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Token extends Model  {

	/**
	 * The database table used by the model.
	 *
	 * @var string
	 */
	protected $table = 'token';
    
    public static function updateToken($user_id, $token){
        $tokenObj = Token::where('user_id', $user_id)->first();

        if($tokenObj){
            Token::where('id', $tokenObj->id)
            ->update(array(
                'user_id' => $user_id,
                'token' => $token,
                'timestamp' => time(),
            ));
            
        }else{
            Token::insert(array(
                'user_id' => $user_id,
                'token' => $token,
                'timestamp' => time(),
            ));
        }
    }
    

}