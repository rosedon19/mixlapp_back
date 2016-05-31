<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Friend extends Model
{
    /**
	 * The database table used by the model.
	 *
	 * @var string
	 */
	protected $table = 'friends';
	public $timestamps = false;
	protected $primaryKey = 'id';
    
    /**
     * @desc checks if two users are friends with each other
     * @param $user1 integer : user id of 1st user
     * @param $users integer : user id of 2nd user
     **/
    public static function areFriends($user1, $user2) {
        $a = Friend::where('user_a_id', $user1)
            ->where('user_b_id', $user2)
            ->where('accepted_by_a', 1)
            ->where('accepted_by_b', 1)
            ->count();
        if($a <= 0) {
            $b = Friend::where('user_a_id', $user2)
                ->where('user_b_id', $user1)
                ->where('accepted_by_a', 1)
                ->where('accepted_by_b', 1)
                ->count();
            return $b > 0;
        }
        return true;
    }
}
