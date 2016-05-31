<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserLocation extends Model
{
    /**
	 * The database table used by the model.
	 *
	 * @var string
	 */
	protected $table = 'user_locations';
	public $timestamps = false;
	protected $primaryKey = 'id';
	
    
    public static function updateUserLocation($userId, $longitude, $latitude) {
        $location = UserLocation::where('user_id', $userId)->first();
        if(empty($location)) {
            $location = new UserLocation;
            $location->user_id = $userId;
        }
        $location->longitude = $longitude;
        $location->latitude = $latitude;
        $location->updated_at = date('Y-m-d H:i:s');
        $location->save();
        return $location;
    }
    
	/**
     * @desc calculate distance between two points given by longitude and latitude in miles
     * @param long1
     * @param lat1
     * @param long2
     * @param lat2
     * @return distance in miles
     */
    public static function calculateDistance($long1, $lat1, $long2, $lat2) {
        $earthRadius = 3959;
        $lat1 = deg2rad($lat1);
        $long1 = deg2rad($long1);
        $lat2 = deg2rad($lat2);
        $long2 = deg2rad($long2);

        $lonDelta = $long2 - $long1;

        $a = pow(cos($lat2) * sin($lonDelta), 2) +
            pow(cos($lat1) * sin($lat2) - sin($lat1) * cos($lat2) * cos($lonDelta), 2);
        $b = sin($lat1) * sin($lat2) + cos($lat1) * cos($lat2) * cos($lonDelta);

        $angle = atan2(sqrt($a), $b);
        return $angle * $earthRadius;
    }
}
