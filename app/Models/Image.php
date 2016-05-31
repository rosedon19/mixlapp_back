<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Image extends Model
{
    /**
	 * The database table used by the model.
	 *
	 * @var string
	 */
	protected $table = 'images';
	public $timestamps = false;
	protected $primaryKey = 'id';
    
    const IMAGE_TYPE_PROFILE = 'profile';
    const IMAGE_TYPE_OFFER = 'offer';
    const IMAGE_TYPE_CHAT = 'chat';
    
    /**
	 * @desc get profile images of a user
	 * @param $userId
	 * @return array images
	 */
    public static function getUserProfileImages($userId) {
        $images = Image::where('user_id', $userId)
            ->where('type', Image::IMAGE_TYPE_PROFILE)
            ->select('image_path', 'rel_id')
            ->get();
        $res = [];
        foreach($images as $img) {
            if (!preg_match("~^(?:f|ht)tps?://~i", $img->image_path)) {
                $res [$img->rel_id] = asset($img->image_path);
            } else {
                $res [$img->rel_id] = $img->image_path;
            }
        }
        return $res;
    }
    
    /**
	 * @desc save a profile image
	 * @param $userId
     * @param $seq
     * @param $imagePath
	 * @return object image
	 */
    public static function putUserProfileImage($userId, $seq, $imagePath) {
        $img = Image::where('user_id', $userId)
            ->where('type', Image::IMAGE_TYPE_PROFILE)
            ->where('rel_id', $seq)
            ->first();
        if(empty($img)) {
            $img = new Image;
            $img->type = Image::IMAGE_TYPE_PROFILE;
            $img->user_id = $userId;
            $img->rel_id = $seq;
            $img->created_at = date('Y-m-d H:i:s');
        }
        $img->image_path = $imagePath;
        $img->updated_at = date('Y-m-d H:i:s');
        $img->save();
        return $img;
    }
    
    /**
	 * @desc get profile images of a user
	 * @param $userId
	 * @return array images
	 */
    public static function getOfferImage($userId, $offerId) {
        $image = Image::where('user_id', $userId)
            ->where('type', Image::IMAGE_TYPE_OFFER)
            ->where('rel_id', $offerId)
            ->select('image_path')
            ->first();
        if($image) {
//            $image = $image->toArray();
            if (!preg_match("~^(?:f|ht)tps?://~i", $image['image_path'])) {
                $image['image_path'] = asset($image['image_path']);
            } 
            return $image;
        }
        return false;
    }
    
    /**
	 * @desc save a profile image
	 * @param $userId
     * @param $seq
     * @param $imagePath
	 * @return object image
	 */
    public static function putOfferImage($userId, $offerId, $imagePath) {
        $img = Image::where('user_id', $userId)
            ->where('type', Image::IMAGE_TYPE_OFFER)
            ->where('rel_id', $offerId)
            ->first();
        if(empty($img)) {
            $img = new Image;
            $img->user_id = $userId;
            $img->type = Image::IMAGE_TYPE_OFFER;
            $img->rel_id = $offerId;
            $img->created_at = date('Y-m-d H:i:s');
        }
        $img->image_path = $imagePath;
        $img->updated_at = date('Y-m-d H:i:s');
        $img->save();
        return $img;
    }
}
