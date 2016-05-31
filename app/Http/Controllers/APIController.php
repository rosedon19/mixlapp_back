<?php
use App\Models\User;
use App\Models\Preference;
use App\Models\IoToken;
use App\Models\Token;
use App\Models\UserLocation;
use App\Models\CreditCard;
use App\Models\Contact;
use App\Models\Friend;
use App\Models\Image;
use App\Models\Message;
use App\Models\Notification;
use App\Models\Offer;
use App\Models\Invite;
use App\Models\Venue;
use App\Models\Cities;
use App\Models\States;

class APIController extends BaseController {
    public function __construct(){
        $this->_currentUser = '';
        $this->_getCurrentUser();
        $this->data = array(
            'error' => false,
            'messages' => array(),
        );
    }
    
    public function _getCurrentUser(){    
        $headers = apache_request_headers();
        $token = trim(isset($headers['authorized_token']) ? $headers['authorized_token'] : '');
        if(!$token){
            $token = trim(isset($headers['Authorized_token']) ? $headers['Authorized_token'] : '');
        }
        if($token){
            $tokenObj = Token::where('token', $token)->first();
            if($tokenObj){
                $this->_currentUser = User::getUserById($tokenObj->user_id);
                if(!empty($this->_currentUser))
                    return $this->_currentUser;
            }
        }
        return false;
    }
    
    public function getGlobalCurrentId(){
        $headers = apache_request_headers();
        isset($headers['Authorization']) && ($api_key = $headers['Authorization']);
        if(isset($api_key)){
            $this->user_id = $this->user_model->getUserId($api_key); 
        }elseif($api_key = Input::get('api_key', false)){
            $this->user_id = $this->user_model->getUserId($api_key);            
        }
    }
    
    private function _JsonOutput(){
        header("access-control-allow-origin: *");
        response()->json($this->data)->send();
        exit;
    }
    
    private function _createToken(){
        $len = rand(1,1000);
        $token = md5(time().$len);
        $row = Token::where('token', $token)->first();
        if($row){
            $token = $this->_createToken();
        }
        return $token;
    }
    
    private function _insertToken($user_id){
        $token = $this->_createToken();
        Token::updateToken($user_id, $token);
        return $token;
    }
    
    public function getIndex(){
    }
    
    private function generateApiKey() {
        return md5(uniqid(rand(), true));
    }
    
    public function updateDeviceToken($user_id){
        if(Input::get('io_token', false)){
            IoToken::updateToken($user_id, Input::get('io_token'));
        }
    }
    
    /**
     * @desc creates a user record in db
     * @param $data : array : data
     */
    protected function _createUser(array $data) {
        if(isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }
        $user = array(
            'email' => $data['email'],
            'password' => isset($data['password']) ? $data['password'] : null,
            'type' => $data['type'],
            'created_at' => date('Y-m-d H:i:s')
        );
        $user = array_filter($user);
        $user_id = User::insertGetId($user);
        if($user_id){
            if($data['type'] === 'u') {
                $contact = new Contact;
                $contact->user_id = $user_id;
                $contact->firstname = isset($data['firstname']) ? $data['firstname'] : '';
                $contact->lastname = isset($data['lastname']) ? $data['lastname'] : '';
                $contact->gender = isset($data['gender']) ? $data['gender'] : 'n';
                $contact->date_of_birth = isset($data['date_of_birth']) ? $data['date_of_birth'] : '0000-00-00';
                $contact->description = isset($data['description']) ? $data['description'] : '';
                $contact->save();
            } elseif($data['type'] === 'v') {
                $venue = new Venue;
                $venue->user_id = $user_id;
                $venue->businessname = isset($data['businessname']) ? $data['businessname'] : '';
                $venue->address = isset($data['address']) ? $data['address'] : '';
                $venue->city = isset($data['city']) ? $data['city'] : '';
                $venue->state = isset($data['state']) ? $data['state'] : '';
                $venue->zipcode = isset($data['zipcode']) ? $data['zipcode'] : '';
                $venue->description = isset($data['description']) ? $data['description'] : '';
                $venue->save();
            }
            //create preference record
            $preference = new Preference;
            $preference->user_id = $user_id;
            $preference->save();
            //create location record
            $location = new UserLocation;
            $location->user_id = $user_id;
            $location->updated_at = date('Y-m-d H:i:s');
            $location->save();
            return User::getUserById($user_id);
        }
        return false;
    }
    
    /**
     * @desc update user profile
     */
    public function _updateUser($userId, $data) {
        //check token
        $user = User::getUserById($userId);
        if($user) {
            if($user->isContact()) {
                $dataKeys = array('firstname', 'lastname', 'gender', 'date_of_birth', 'description');
                $info = Contact::where('user_id', $user->id)->first();
            } elseif($user->isVenue()) {
                $dataKeys = array('businessname', 'address', 'city', 'state', 'zipcode', 'description');
                $info = Venue::where('user_id', $user->id)->first();
            }
            foreach($dataKeys as $k) {
                $v = Input::get($k, null);
                if($v !== null) {
                    $info->$k = $v;
                }
            }
            $info->save();
            //
            $dataKeys = array('email', 'active');
            foreach($dataKeys as $k) {
                $v = Input::get($k, null);
                if($v !== null) {
                    $user->$k = $v;
                }
            }
            if(Input::get('password', null) !== null) {
                $user->password = Hash::make(Input::get('password'));
            }
            $user->updated_at = date('Y-m-d H:i:s');
            $user->save();
            return $user;
        } else {
            return false;
        }
    }
    
    /**
     * @desc creates a json object for register/login success response
     * @param $user : object : user model object
     * @param $updateToken : boolean : true when needed token update, otherwise false
     */
    protected function _getUser($id, $updateToken = true, $findBy='id') {
        $userOnlineUpdateMax = 15 * 60; //15 min
        if($findBy === 'id') {
            $user = User::where('id', $id)->select('id','type','email','active')->first();
        } elseif ($findBy === 'email') {
            $user = User::where('email', $id)->select('id','type','email','active')->first();
        }
        if(!$user)
            return false;
        //updating user's login token
        if($updateToken) {
            $token = $this->_insertToken($user->id);
            $user->token = $token;
        }
        //fetching user preference
        if($user->type === 'u') {
            $info = Contact::where('user_id', $user->id)->select('firstname', 'lastname', 'gender', 'date_of_birth', 'description')->first();
            $preference = Preference::where('user_id', $user->id)->select('user_id', 'search_radius', 'age_range_min', 'age_range_max', 'friend_gender', 'who_can_see_me', 'who_can_contact_me', 'accept_friend_request', 'accept_invites')->first();
        } elseif($user->type === 'v') {
            $info = Venue::where('user_id', $user->id)->select('businessname', 'address', 'city', 'state', 'zipcode', 'description')->first();
            $preference = Preference::where('user_id', $user->id)->select('user_id', 'search_radius', 'age_range_min', 'age_range_max', 'friend_gender', 'who_can_see_me', 'who_can_contact_me', 'page_visibility', 'accept_messages')->first();
        }else{
            $preference = null;
        }
        $location = UserLocation::where('user_id', $user->id)->first();
        
        //
        $data = [
            'user' => array_merge( $user->toArray(), empty($info) ? [] : $info->toArray() ),
            'preference' => ($preference!=null ? $preference->toArray() : []),
            'location' => ($location!=null ? $location->toArray() : [] ),
        ];
        $images = Image::getUserProfileImages($user->id);
        $data['user']['images'] = [];
        foreach($images as $seq=>$imagePath) {
            $data['user']['images'] [] = $imagePath;
        }
        $data['user']['online'] = (time() - strtotime($location->updated_at) < $userOnlineUpdateMax) ? 1 : 0;
        return $data;
    }
    
    protected function _getUserPublic($userId) {
        $u = $this->_getUser($userId, false);
        if($u) {
            unset($u['user']['email']);
            unset($u['preference']['id']);
            unset($u['preference']['user_id']);
            unset($u['preference']['search_radius']);
            unset($u['preference']['age_range_min']);
            unset($u['preference']['age_range_max']);
            unset($u['preference']['friend_gender']);
            unset($u['location']['id']);
            unset($u['location']['user_id']);
            $u = array_merge($u['user'], $u['preference'], $u['location']);
        }
        return $u;
    }
    
    /**
     * @desc Image File upload processor
     * @param $fileObj Symfony\Component\HttpFoundation\File\UploadedFile object
     * @param $moveTo string path to which the file will be moved(relative path from app's upload storage)
     */
    protected function _processUploadedImage($fileObj, $moveTo) {
        $maxFileSize = 1024000; //1 MB
        $allowedFileExts = array('gif', 'jpg', 'png', 'jpeg');
        $imagesBasePathRel = '/assets/uploads/images';
        $imagesBasePathAbs = dirname(dirname(dirname(dirname(__FILE__)))) . '/assets/uploads/images';
        
        $target = $imagesBasePathAbs . $moveTo;
        $targetdir = dirname($target);
        if (!file_exists($targetdir) && !is_dir($targetdir)) {
            mkdir($targetdir, 0777, true);
        }
        if(!in_array($fileObj->getClientOriginalExtension(), $allowedFileExts)) {
            return false;
        }
        if($fileObj->isValid()) {
           $fileObj->move($targetdir, basename($moveTo));
           return $imagesBasePathRel . $moveTo;
        }
        return false;
    }
    
    
    protected function _processUserProfileImageInput($userId) {
        for($i=1; $i<=5; $i++) {
            $imgPath = null;
            if(Input::hasFile('image'.$i)) { //when file is being uploaded
                $file = Input::file('image'.$i);
                $imgPath = $this->_processUploadedImage($file, '/users/u'.$userId.'/image'.$i.'.'.$file->getClientOriginalExtension());
            } elseif(Input::has('image'.$i)) {
                $imgPath = Input::get('image'.$i);
            }
            if(!empty($imgPath)) {
                Image::putUserProfileImage($userId, $i, $imgPath);
            }
        }
    }
    /**
     * POST /register
     */
    public function postRegister(){
        $rules = array(
            'type' => 'required',
            'email' => 'required|email',
            'password' => 'required'
        );
        $validator = Validator::make(Input::all(), $rules);
        if ($validator->fails())
        {
            $this->data = array(
                'error' => true,
                'messages' => $validator->messages()->all(),
            );
        }else{
            $db_user = User::where('email', Input::get('email'))->first();
            if(empty($db_user)){
                $user = $this->_createUser(Input::all());
                if( $user ){
                    $this->updateDeviceToken($user->id);
                    $this->_processUserProfileImageInput($user->id);
                    $this->data = $this->_getUser($user->id);
                    $this->data['error'] = false;
                    $this->data['messages'] = [ 'You are successfully registered' ];
                } else {
                    $this->data['error'] = true;
                    $this->data['messages'] = [ 'Oops! An error occurred while registering' ];
                }
            } else {
                $this->data['error'] = true;
                $this->data['messages'] = [ 'Sorry, this email already exists' ];
            }
        }   
        $this->_JsonOutput();
    }
    
    /**
     * POST /login
     */
    public function postLogin(){
        $rules = array(
            'password' => 'required',
            'email' => 'required|email'
        );
        $validator = Validator::make(Input::all(), $rules);
        if ($validator->fails())
        {
            $this->data = array(
                'error' => true,
                'messages' => $validator->messages()->all(),
            );
        }else{
            $creds = array(
                'email' => Input::get('email'),
                'password' => Input::get('password'),
            );
            if(Auth::attempt($creds)){
                //fetching user record
                $this->data = $this->_getUser(Input::get('email'), true, 'email');
                $this->updateDeviceToken($this->data['user']['id']);
                $this->data['error'] = false;
                $this->data['messages'] = [ 'You have successfully logged in.' ];
            } 
            else{
                $this->data['error'] = true;
                $this->data['messages'] =  [ 'Login failed. Incorrect credentials' ];
            }
        }
        return response()->json($this->data);
    }

    /**
     * POST /fblogin
     */
    public function postFblogin(){
        $rules = array(
            'type' => 'required',
            'email' => 'required|email',
            'fb_token' => 'required',
        );
        $validator = Validator::make(Input::all(), $rules);
        $user_id = 0;
        if ($validator->fails())
        {
            $this->data = array(
                'error' => true,
                'messages' => $validator->messages()->all(),
            );
        }else{
            $db_user = User::where('email', Input::get('email'))->first();
            if($db_user){
                $user_id = $db_user->id;
                if($db_user->fb_token == Input::get('fb_token') || $db_user->fb_token==''){
                    $this->updateDeviceToken($user_id);
                    $this->_updateUser($user_id, Input::all());
                    $this->_processUserProfileImageInput($user_id);
                    $res = true;
                }else{
                    $this->data['messages'][] = 'Your FB token doesn\'t match with the prior token';
                    $res = false;
                }
            } else {
                //if not saved in our user db, then create a record
                $user = $this->_createUser(Input::all());
                if($user!==false) {
                    $user_id = $user->id;
                    $this->_processUserProfileImageInput($user->id);
                    $res = true;
                } else {
                    $res = false;
                }
            }
            
            if ($res) {
                $this->data = $this->_getUser($user_id);
                $this->data['error'] = false;
                $this->data['messages'] = [ 'You have successfully logged in.' ];
            } else {
                $this->data['error'] = true;
                $this->data['messages'][] = 'Oops! An error occurred while registering';
            } 
        }   
        return response()->json($this->data);
    }
    
    /**
     * POST /forgotpassword
     */
    public function postForgotpassword(){
        $rules = array(
            'username' => 'required',
        );
        $validator = Validator::make(Input::all(), $rules);
        if ($validator->fails())
        {
            $this->data = array(
                'error' => true,
                'messages' => $validator->messages()->all(),
            );
            $this->_JsonOutput();
        }
        
        $result = $this->user_model->forgotPassword(Input::get('username'));
        if($result['error'] === true){
            $this->data = array(
                'error' => true,
                'messages' => $result['messages'],
            );
        }else{
            $link = URL::to('forgot-password/'.$result['messages']);
            $params = array(
                'email' => urlencode($result['email']),
                'subject' => urlencode('Forgot password'),
                'messages' => urlencode('Please reset your password at this link, {$link}'),
                'from' => urlencode('locb@locb.com'),
            );
            $postData = '';
           //create name value pairs seperated by &
           foreach($params as $k => $v) 
           { 
              $postData .= $k . '='.$v.'&'; 
           }
           rtrim($postData, '&');
           $url = 'http://b484maps.com/mail.php';
         
            $ch = curl_init();  
         
            curl_setopt($ch,CURLOPT_URL,$url);
            curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
            curl_setopt($ch,CURLOPT_HEADER, false); 
            curl_setopt($ch, CURLOPT_POST, count($postData));
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);    
         
            $output=curl_exec($ch);
         
            curl_close($ch);
//            file_get_contents('http://b484maps.com/mail.php?email={$email}&subject={$subject}&message={$message}&from={$from}');
//            mail($params['email'], $params['subject'], $params['messages']);
//            echo           'http://b484maps.com/mail.php?email={$email}&subject={$subject}&message={$message}&from={$from}';exit;
        }
        $this->_JsonOutput();
    }
    
    /**
     * @desc update user profile
     * POST /user
     */
    public function postUser() {
        //check token
        $authUser = $this->_getCurrentUser();
        if($authUser !== false) {
            $doUpdate = true;
            if(Input::has('email') && Input::get('email', '') != '' && $authUser->email != Input::get('email')) {
                $db_user = User::where('email', Input::get('email'))->where('id', '<>', $authUser->id)->first();
                if(!empty($db_user)){
                    $this->data['error'] = true; 
                    $this->data['messages'] = [ 'Sorry, this email already exists' ];
                    $doUpdate = false;
                }
            }
            if($doUpdate) {
                $this->updateDeviceToken($authUser->id);
                $this->_updateUser($authUser->id, Input::all());
                $this->_processUserProfileImageInput($authUser->id);
                $this->data = $this->_getUser($authUser->id, false);
                unset($this->data['preference']);
                $this->data['error'] = false; 
                $this->data['messages'] = [ 'User profile has been updated.' ];
            }
        } else {
            $this->data['error'] = true;
            $this->data['messages'] = [ 'User is not authorized.' ];
        }
        $this->_JsonOutput();
    }
    
    /**
     * @desc update user preference
     * POST /user
     */
    public function postPreference() {
        //check token
        $authUser = $this->_getCurrentUser();
        if($authUser !== false) {
            $preference = Preference::where('user_id', $authUser->id)->first();
            if(empty($preference)) {
                $preference = new Preference;
                $preference->user_id = $authUser->id;
            }
            $dataKeys = array(
                'search_radius', 'age_range_min', 'age_range_max',
                'friend_gender', 'who_can_see_me', 'who_can_contact_me',
                'accept_friend_request', 'accept_invites',
                'page_visibility', 'accept_messages'
            );
            foreach($dataKeys as $k) {
                $v = Input::get($k, null);
                if($v !== null) {
                    $preference->$k = $v;
                }
            }
            $preference->save();
            $this->data = $this->_getUser($authUser->id, false);
            unset($this->data['user']);
            $this->data['error'] = false;
            $this->data['messages'] = [ 'User preference has been updated.' ];
        } else {
            $this->data['error'] = true;
            $this->data['messages'] = [ 'User is not authorized.' ];
        }
        $this->_JsonOutput();
    }
    
    /**
     * @desc update user's current location
     * POST /location
     */
    public function postLocation() {
        //check token
        $authUser = $this->_getCurrentUser();
        if($authUser !== false) {
            $rules = array(
                'longitude' => 'required',
                'latitude' => 'required',
            );
            $validator = Validator::make(Input::all(), $rules);
            if ($validator->fails()) {
                $this->data = array(
                    'error' => true,
                    'messages' => $validator->messages()->all(),
                );      
            } else {
                UserLocation::updateUserLocation($authUser->id, Input::get('longitude'), Input::get('latitude'));
                $this->data = array(
                    'error' => false,
                    'messages' => [ 'User location has been updated.' ]
                ); 
            }
        } else {
            $this->data['error'] = true;
            $this->data['messages'] = [ "User is not authorized." ];
        }
        $this->_JsonOutput();
    }
    
    /**
     * @desc update user unread messages and marked them as read
     * GET /messages
     * @param sender
     */
    public function getMessages() {
        //check token
        $authUser = $this->_getCurrentUser();
        if($authUser !== false) {
            $chats = Message::where('receiver', $authUser->id)
                ->where('is_read', 0);
            if(Input::get('sender', null) !== null) {
                $chats = $chats->where('sender', Input::get('sender'));
            }
            $chats = $chats->get();
            foreach($chats as $chat) {
                $chat->markAsRead();
            }
            $this->data = array(
                'error' => false,
                'chats' => $chats->toArray(),
                'messages' => []
            ); 
        } else {
            $this->data['error'] = true;
            $this->data['messages'] = [ "User is not authorized." ];
        }
        $this->_JsonOutput();
    }
    
    /**
     * @desc send a message to another user
     * POST /messages
     * @param receiver required
     * @param content t|i
     * @param image FILE required when content=i
     * @param text string required when content=t
     */
    public function postMessages() {
        //check token
        $authUser = $this->_getCurrentUser();
        if($authUser !== false) {
            $rules = array(
                'receiver' => 'required',
            );
            $validator = Validator::make(Input::all(), $rules);
            if ($validator->fails()) {
                $this->data = array(
                    'error' => true,
                    'messages' => $validator->messages()->all(),
                );      
            } else {
                $content = Input::get('content', Message::MESSAGE_CONTENT_TEXT);
                $err = true;
                $message = null;
                if($content === Message::MESSAGE_CONTENT_IMAGE && Input::hasFile('image')) { //when file is being uploaded
                    $file = Input::file('image');
                    $imgPath = $this->_processUploadedImage($file, '/chat_images/u'.$authUser->id . '_u' . Input::get('receiver') . '_' . time() . '.' . $file->getClientOriginalExtension());
                    if($imgPath) {
                        $message = Message::putMessage($authUser->id, Input::get('receiver'), $content, $imgPath);
                        $err = false;
                    }
                } elseif ($content === Message::MESSAGE_CONTENT_TEXT && Input::has('text')) {
                    $text = Input::get('text', '');
                    if(!empty($text)) {
                        $message = Message::putMessage($authUser->id, Input::get('receiver'), $content, Input::get('text', ''));
                        $err = false;
                    }
                }
                if($message) {
                    //Send push notification to the receiver of the message
                    $result = Notification::sendNotification(
                        $authUser->id,
                        Input::get('receiver'),
                        IoToken::getUserToken(Input::get('receiver')),
                        [
                            'title'=>'New message arrived',
                            'body'=> Contact::getFullName($authUser->id) .' sent you a message.',
                            'custom'=> [
                                'push_type' => "4",
                                'message_id' => $message->id,
                                'sender' => $message->sender,
                                'content'=>$message->content,
                                'sent_at'=>$message->sent_at
                            ]
                        ]
                    );
                    $this->data = array(
                        'error' => false,
                        'message_id' => $message->id,
                        'messages' => [ 'Message has been sent.' ]
                    );
                } else {
                    $this->data = array(
                        'error' => true,
                        'messages' => [ 'Message contains error.' ]
                    );
                } 
            }
        } else {
            $this->data['error'] = true;
            $this->data['messages'] = [ "User is not authorized." ];
        }
        $this->_JsonOutput();
    }
    
    /**
     * @desc update user preference
     * GET  /nearby
     * @param type required all|people|friends|venues
     * @param longitude
     * @param latitude
     */
    public function getNearby() {

        //check token
        $authUser = $this->_getCurrentUser();
        if($authUser !== false) {
            $rules = array(
                'type' => 'required',
            );
            $validator = Validator::make(Input::all(), $rules);
            if ($validator->fails()) {
                $this->data = array(
                    'error' => true,
                    'messages' => $validator->messages()->all(),
                );      
            } else {
                $dbLocation = UserLocation::where('user_id', $authUser->id)->first();
                $preference = Preference::where('user_id', $authUser->id)->first();
                //
                $type = Input::get('type');
                $longitude = (float)Input::get('longitude', $dbLocation->longitude);
                $latitude = (float)Input::get('latitude', $dbLocation->latitude);
                $search_radius = (float)Input::get('search_radius', $preference->search_radius);
                //
                //Find all users near me:
                $res = DB::table('user_locations')
                    ->join('users', 'user_locations.user_id', '=', 'users.id')
                    ->join('preferences', 'user_locations.user_id', '=', 'preferences.user_id')
                    ->leftJoin('contacts', 'user_locations.user_id', '=', 'contacts.user_id')
                    ->selectRaw('user_locations.longitude as longitude, user_locations.latitude as latitude '
                        .',users.id as user_id, users.type as type'
                        .',contacts.date_of_birth, contacts.gender as gender'
                        .',preferences.who_can_see_me as who_can_see_me, preferences.page_visibility as page_visibility')
                    ->where('users.active', '=' , '1');
                //age preference
                $minAge = $preference->age_range_min ? $preference->age_range_min : 0;
                $maxAge = $preference->age_range_max ? $preference->age_range_max : 200;
                $res->whereRaw("(users.type <> 'u' or (DATEDIFF(CURRENT_DATE, contacts.date_of_birth) / 365.25 >= $minAge and DATEDIFF(CURRENT_DATE, contacts.date_of_birth) / 365.25 <= $maxAge))");
                //gender preference
                if($preference->friend_gender === 'm' || $preference->friend_gender === 'f')
                    $res = $res->whereRaw("(users.type <> 'u' or contacts.gender = '{$preference->friend_gender}')");
                //venue's page visibility
                $res = $res->whereRaw("(users.type <> 'v' or preferences.page_visibility = 1)");
                //
                $res = $res->get();
                $found = [];
                foreach($res as $r) {
                    if(UserLocation::calculateDistance($longitude, $latitude, $r->longitude, $r->latitude) <= $search_radius) {
                        //it's in the search radius
                        if($type === 'all' || 
                            ($type==='people' && $r->type==='u') ||
                            ($type==='friends' && Friend::areFriends($authUser->id, $r->user_id)) ||
                            ($type==='venues' && $r->type==='v')) {
                            if($r->type === 'u') {
                                $resType = 'contact';
                                if($r->who_can_see_me ==='n' || ($r->who_can_see_me ==='f' && !Friend::areFriends($authUser->id, $r->user_id))) {
                                    continue;
                                }
                            } elseif($r->type === 'v') {
                                $resType = 'venue';
                            }
                            $found []= array( 'id'=>$r->user_id, 'type' => $resType,  'profile'=> $this->_getUserPublic($r->user_id) );
                        }
                    }
                }
                $this->data['error'] = false;
                $this->data['found'] = $found;
                $this->data['messages'] = [ ];
            }
        } else {
            $this->data['error'] = true;
            $this->data['messages'] = [ "User is not authorized." ];
        }
        $this->_JsonOutput();
    }
    
    /**
     * @desc update user preference
     * GET  /search
     * @param type required all|people|friends|venues|nearby
     * @param name
     * @param age
     * @param gender
     */
    public function getSearch() {
        //check token
        $authUser = $this->_getCurrentUser();
        if($authUser !== false) {
            $rules = array(
                'type' => 'required',
            );
            $validator = Validator::make(Input::all(), $rules);
            if ($validator->fails()) {
                $this->data = array(
                    'error' => true,
                    'messages' => $validator->messages()->all(),
                );      
            } else {
                $dbLocation = UserLocation::where('user_id', $authUser->id)->first();
                $preference = Preference::where('user_id', $authUser->id)->first();
                //
                $longitude = (float)$dbLocation->longitude;
                $latitude = (float)$dbLocation->latitude;
                //
                $type = Input::get('type');
                $name = Input::get('name', '');
                $gender = Input::get('gender', '');
                if(strtolower($gender) === 'male')
                    $gender = 'm';
                elseif(strtolower($gender) === 'female')
                    $gender = 'f';
                $age = Input::get('age', '');
                //
                //Find all users near me:
                $res = DB::table('users')
                    ->join('preferences', 'users.id', '=', 'preferences.user_id')
                    ->leftJoin('contacts', 'users.id', '=', 'contacts.user_id')
                    ->leftJoin('venues', 'users.id', '=', 'venues.user_id')
                    ->leftJoin('user_locations', 'users.id', '=', 'user_locations.user_id')
                    ->selectRaw('user_locations.longitude as longitude, user_locations.latitude as latitude '
                        .',users.id as user_id, users.type as type'
                        .',contacts.date_of_birth, contacts.gender as gender'
                        .',preferences.who_can_see_me as who_can_see_me, preferences.page_visibility as page_visibility')
                    ->where('users.active', '=' , '1');
                //age
                if(!empty($age)) { //if specified age in the search parameter
                    $res->whereRaw("(users.type = 'u' and FLOOR(DATEDIFF(CURRENT_DATE, contacts.date_of_birth) / 365.25) = $age)");
                } else { //if not specified the age in the search parameter, use preference setting
                    $minAge = $preference->age_range_min ? $preference->age_range_min : 0;
                    $maxAge = $preference->age_range_max ? $preference->age_range_max : 200;
                    $res->whereRaw("(users.type <> 'u' or (DATEDIFF(CURRENT_DATE, contacts.date_of_birth) / 365.25 >= $minAge and DATEDIFF(CURRENT_DATE, contacts.date_of_birth) / 365.25 <= $maxAge))");
                }
                //gender
                if(!empty($gender)) { //if specified gender in the search parameter
                    $res = $res->where("contacts.gender", "=", $gender);
                } else {  //if not specified, use preference setting
                    if($preference->friend_gender === 'm' || $preference->friend_gender === 'f')
                        $res = $res->whereRaw("(users.type <> 'u' or contacts.gender = '{$preference->friend_gender}')");
                }
                //name
                if(!empty($name)) { //if specified name in the search parameter
                    $res = $res->whereRaw("((users.type = 'u' and CONCAT(contacts.firstname,' ',contacts.lastname) LIKE '%{$name}%') or (users.type = 'v' and venues.businessname LIKE '%{$name}%'))");
                }
                //venue's page visibility
                $res = $res->whereRaw("(users.type <> 'v' or preferences.page_visibility = 1)");
                //
                $search_radius = (float)$preference->search_radius;
                //
                $res = $res->get();
                $found = [];
                foreach($res as $r) {
                    if($type === 'all' || 
                        ($type==='people' && $r->type==='u') ||
                        ($type==='friends' && Friend::areFriends($authUser->id, $r->user_id)) ||
                        ($type==='venues' && $r->type==='v') ||
                        ($type==='nearby' && UserLocation::calculateDistance($longitude, $latitude, $r->longitude, $r->latitude) <= $search_radius) ) {
                        if($r->type === 'u') {
                            $resType = 'contact';
                            if($r->who_can_see_me ==='n' || ($r->who_can_see_me ==='f' && !Friend::areFriends($authUser->id, $r->user_id))) {
                                continue;
                            }
                        } elseif($r->type === 'v') {
                            $resType = 'venue';
                        }
                        $found []= array( 'id'=>$r->user_id, 'type' => $resType,  'profile'=> $this->_getUserPublic($r->user_id) );
                    }
                }
                $this->data['error'] = false;
                $this->data['found'] = $found;
                $this->data['messages'] = [ ];
            }
        } else {
            $this->data['error'] = true;
            $this->data['messages'] = [ "User is not authorized." ];
        }
        $this->_JsonOutput();
    }
    
    
    
    /**
     * $desc get user's public profile
     * POST /login
     * @param id : when not given or empty, authenticated user will be returned, if array is given, array of users will be returned.
     */
     public function getUser() {
        //check token
        $authUser = $this->_getCurrentUser();
        if($authUser !== false) {
            $ids = Input::get('id', $authUser->id);
            if(!is_array($ids)) {
                $ids = array($ids);
            }
            $result = [];
            foreach($ids as $id) {
                $result []= $this->_getUserPublic($id);
            }
            $this->data['users'] = $result;
            $this->data['error'] = false;
            $this->data['messages'] = [];
        } else {
            $this->data['error'] = true;
            $this->data['messages'] = [ "User is not authorized." ];
        }
        $this->_JsonOutput(); 
    }
     
    /**
     * @desc get friend list(including newly requested)
     * GET /friend
     */
    public function getFriend() {
        //check token
        $authUser = $this->_getCurrentUser();
        if($authUser !== false) {
            $friendListA = Friend::where('user_a_id', $authUser->id)->get();
            $friendListB = Friend::where('user_b_id', $authUser->id)->get();
            $this->data['error'] = false;
            $result = [];
            foreach($friendListA as &$f) {
                $result []= array( 'id' => $f->id, 'friend_user_id' => $f->user_b_id, 'friend_accepted' => $f->accepted_by_b, 'user_accepted' => $f->accepted_by_a, 'friend_profile'=> $this->_getUserPublic($f->user_b_id) );
                    
            }
            foreach($friendListB as &$f) {
                $result []= array( 'id' => $f->id, 'friend_user_id' => $f->user_a_id, 'friend_accepted' => $f->accepted_by_a, 'user_accepted' => $f->accepted_by_b, 'friend_profile'=> $this->_getUserPublic($f->user_a_id) );
            }
            $this->data['friends'] = $result;
            $this->data['messages'] = [];
        } else {
            $this->data['error'] = true;
            $this->data['messages'] = [ "User is not authorized." ];
        }
        $this->_JsonOutput();
    }
    /**
     * @desc accept/deny a friend from the list (including newly requested)
     * POST /friend
     * @param friend_user_id
     * @param user_accept
     */
    public function postFriend() {
        //check token
        $authUser = $this->_getCurrentUser();
        if($authUser !== false) {
            $rules = array(
                'friend_user_id' => 'required',
            );
            $validator = Validator::make(Input::all(), $rules);
            if ($validator->fails()) {
                $this->data = array(
                    'error' => true,
                    'messages' => $validator->messages()->all(),
                );      
            } else {
                $friend_user_id = Input::get('friend_user_id');
                $user_accept = Input::get('user_accept', null);
                //search for existing friendship record
                $friend = Friend::where('user_a_id', $authUser->id)->where('user_b_id', $friend_user_id)->first();
                if(!empty($friend)) { //when the user is sitted at a
                    $friend->accepted_by_a = Input::get('user_accept', $friend->accepted_by_a);
                } else {
                    $friend = Friend::where('user_b_id', $authUser->id)->where('user_a_id', $friend_user_id)->first();
                    if(!empty($friend)) { //when the user is sitted at b
                        $friend->accepted_by_b = Input::get('user_accept', $friend->accepted_by_b);;
                    } else { //when this is a new friend request
                        $friend = new Friend;
                        $friend->user_a_id = $authUser->id;
                        $friend->user_b_id = $friend_user_id;
                        $friend->accepted_by_a = Input::get('user_accept', 1);
                        $friend->accepted_by_b = 0;
                        $friend->created_at = date('Y-m-d H:i:s');
                        
                        //Send push notification to the new friend
                        $result = Notification::sendNotification(
                            $friend->user_a_id,
                            $friend->user_b_id,
                            IoToken::getUserToken($friend->user_b_id),
                            [
                                'title'=>'Friend request',
                                'body'=> Contact::getFullName($friend->user_a_id) .' wants to be a friend of yours.',
                                'custom'=> [
                                    "push_type" => "1",
                                    'friend_id'=>$friend->user_a_id
                                ]
                            ]
                        );
                    }
                }
                $friend->updated_at = date('Y-m-d H:i:s');
                $friend->save();
                $this->data = array(
                    'error' => false,
                    'messages' => 'Successfully built the relationship',
                ); 
            }
        } else {
            $this->data['error'] = true;
            $this->data['messages'] = [ "User is not authorized." ];
        }
        $this->_JsonOutput();
    }
    
    /**
     * @desc for venues: get the list of it's offers
     * @desc for users: get an offer by its id
     * GET /offer
     * @param offer_id require for users
     */
    public function getOffer() {
        //check token
        $authUser = $this->_getCurrentUser();
        if($authUser !== false) {
            if($authUser->isVenue()) {
                $offers = Offer::where('user_id', $authUser->id)
                    ->where('active', 1)
                    ->get()->toArray();
                foreach($offers as &$offer) {
                    $img = Image::getOfferImage($authUser->id, $offer['id']);
                    if($img) {
                        $offer['image'] = $img->image_path;
                    }
                }
                $this->data['error'] = false;
                $this->data['offers'] = $offers;
                $this->data['messages'] = [ ];
            } elseif($authUser->isContact()) {
                $rules = array(
                    'offer_id' => 'required',
                );
                $validator = Validator::make(Input::all(), $rules);
                if ($validator->fails()) {
                    $this->data = array(
                        'error' => true,
                        'messages' => $validator->messages()->all(),
                    );      
                } else {
                    $offer = Offer::where('id', Input::get('offer_id'))
                        ->where('active', 1)
                        ->first();
                    if($offer) {
                        $img = Image::getOfferImage($offer->user_id, $offer->id);
                        $this->data['error'] = false;
                        $this->data['offer'] = $offer;
                        if($img) {
                            $this->data['offer']['image'] = $img->image_path;
                        }
                        $this->data['messages'] = [ ];
                    } else {
                        $this->data['error'] = true;
                        $this->data['messages'] = [ "No Offer" ];
                    }
                }
            }
        } else {
            $this->data['error'] = true;
            $this->data['messages'] = [ "User is not authorized." ];
        }
        $this->_JsonOutput();
    }
    
    /**
     * @desc for venues only: create/update an offer
     * POST /offer
     */
    public function postOffer() {
        //check token
        $authUser = $this->_getCurrentUser();
        if($authUser !== false && $authUser->isVenue()) {
            $id = Input::get('id', null);
            if(!empty($id)) {
                $offer = Offer::where('id', $id)->first();
            }
            if(empty($offer)) {
                $offer = new Offer;
                $offer->user_id = $authUser->id;
                $offer->created_at = date('Y-m-d H:i:s');
                $offer->active = 1;
            } 
            if($offer->user_id == $authUser->id) {
                $dataKeys = array('title', 'description', 'valid_until', 'voucher_code');
                foreach($dataKeys as $k) {
                    $v = Input::get($k, null);
                    if($v !== null) {
                        $offer->$k = $v;
                    }
                }
                $offer->updated_at = date('Y-m-d H:i:s');
                $offer->save();
                //process image
                $img = false;
                if(Input::hasFile('image')) { //when file is being uploaded
                    $file = Input::file('image');
                    $imgPath = $this->_processUploadedImage($file, '/offers/u'.$authUser->id . '_o' . $offer->id . '_' . time() . '.' . $file->getClientOriginalExtension());
                    if($imgPath) {
                        $img = Image::putOfferImage($authUser->id, $offer->id, $imgPath);
                    }
                }elseif(Input::get('image_from_gallery')){
                    $img = Image::putOfferImage($authUser->id, $offer->id, Input::get('image_from_gallery'));
                }
                $this->data['error'] = false;
                $this->data['offer'] = $offer->toArray();
                if($img) {
                    $this->data['offer']['image'] = asset($img->image_path);
                }
                $this->data['messages'] = [ "Offer has been updated." ];
            } else {
                $this->data['error'] = true;
                $this->data['messages'] = [ "Invalid activity detected." ];
            }
        } else {
            $this->data['error'] = true;
            $this->data['messages'] = [ "User is not authorized." ];
        }
        $this->_JsonOutput();
    }
    
    /**
     * @desc for venues: get the list of it's offers sent
     *      for users: get the list of his invitations sent
     * GET /invite
     * @param filter    all|sent|accepted|closed (default is 'sent')
     */
    public function getInvite() {
        //check token
        $authUser = $this->_getCurrentUser();
        if($authUser !== false) {
            $filter = Input::get('filter', 'sent');
            $invites = Invite::where('host_user_id', $authUser->id);
            if($filter === 'sent') {
                $invites = $invites->where('status', '<' , Invite::STATUS_CLOSED);
            } elseif($filter === 'accepted') {
                $invites = $invites->where('status', Invite::STATUS_ACCEPTED);
            } elseif($filter === 'closed') {
                $invites = $invites->where('status', Invite::STATUS_CLOSED);
            }
            $invites = $invites->get()->toArray();
            foreach($invites as &$invite) {
                $invite['invitee_user_profile'] =  $this->_getUserPublic($invite['invitee_user_id']);
            }
            $this->data['error'] = false;
            $this->data['invites'] = $invites;
            $this->data['messages'] = [];
        } else {
            $this->data['error'] = true;
            $this->data['messages'] = [ "User is not authorized." ];
        }
        $this->_JsonOutput();
    }
    
    /**
     * @desc for venues: send an offer to some users
     *      for users: send an invitation to another user
     * POST /invite
     */
    public function postInvite() {
        //check token
        $authUser = $this->_getCurrentUser();
        if($authUser !== false) {
            //TODO:
            if($authUser->isVenue()) {
                $rules = array(
                    'offer_id' => 'required',
                    'invitee_user_id' => 'required',
                );
                $dataKeys = array('invitee_user_id', 'message', 'offer_id', 'expire_at');
            } elseif($authUser->isContact()) {
                $rules = array(
                    'invitee_user_id' => 'required',
                );
                $dataKeys = array('invitee_user_id', 'message', 'expire_at');
            }
            $validator = Validator::make(Input::all(), $rules);
            if ($validator->fails()) {
                $this->data = array(
                    'error' => true,
                    'messages' => $validator->messages()->all(),
                );
            } else {
                $invitees = Input::get('invitee_user_id');
                if(!is_array($invitees)) {
                    $invitees = array($invitees);
                }
                $res = []; 
                foreach($invitees as $invitee) {
                    $invite = new Invite;
                    $invite->host_user_id = $authUser->id;
                    $invite->invitee_user_id = $invitee;
                    $invite->created_at = date('Y-m-d H:i:s');
                    $invite->status = 0;
                    $dataKeys = array('message', 'offer_id', 'expire_at');
                    foreach($dataKeys as $k) {
                        $v = Input::get($k, null);
                        if($v !== null) {
                            $invite->$k = $v;
                        }
                    }
                    $push_type = "2";
                    if($authUser->isVenue()) {
                        $push_type = "5";
                        $invite->voucher = Invite::generateVoucher();
                    } elseif($authUser->isContact()) {
                        //business logic:
                        //check if this user has an invite which has been sent in the past 3 days and declided by the invitee
                        if(Invite::whereRaw("host_user_id=".$invite->host_user_id." AND invitee_user_id=".$invite->invitee_user_id." AND UNIX_TIMESTAMP('" .date('Y-m-d H:i:s'). "') - UNIX_TIMESTAMP(accepted_at) < 3 * 24 * 3600 AND (status=2 || status=0)")->count() > 0 &&
                                Friend::areFriends($authUser->id, $invite->invitee_user_id) === false) {
                            //if then, skip the invite 
                            continue;
                        }
                        //check if invitee is accepting any invites by his/her preference
                        $inviteePref = Preference::where('user_id', $invite->invitee_user_id)->first();
                        if($inviteePref && $inviteePref->isAcceptingInvites() === false) {
                            //if not accepting invites, skip the invite
                            continue;
                        }
                    }
                    $invite->save();
//                    print_r(IoToken::getUserToken($invite->invitee_user_id));exit;
                    //Send push notification to the invitee
                    $result = Notification::sendNotification(
                        $invite->host_user_id,
                        $invite->invitee_user_id,
                        IoToken::getUserToken($invite->invitee_user_id),
                        [
                            'title'=>'You are invited to a Drink',
                            'body'=> Contact::getFullName($authUser->id) .' invites you to a drink.',
                            'custom'=> [
                                "push_type" => $push_type,
                                'invite_id'=>$invite->id,
                                'host_user_id'=>$invite->host_user_id,
                                'offer_id'=>$invite->offer_id
                            ]
                        ]
                    );
                        
                    $res []= $invite->toArray();
                }
                if($authUser->isContact()) {
                    $res = count($res)>0 ? $res[0] : [];
                }
                $this->data = array(
                    'error' => (count($res)===0),
                    'invite' => $res,
                    'messages' => $validator->messages()->all(),
                ); 
            }
        } else {
            $this->data['error'] = true;
            $this->data['messages'] = [ "User is not authorized." ];
        }
        $this->_JsonOutput();
    }
    
    
    /**
     * @desc for users only: get the list of his invitations received
     * GET /receiveinvite
     * @param filter    all|received|accepted|denied|claimed|closed (default is 'received')
     */
    public function getReceiveinvite() {
        //check token
        $authUser = $this->_getCurrentUser();
        if($authUser !== false) {
            $filter = Input::get('filter', 'received');
            $invites = Invite::leftJoin('offers', 'invites.offer_id', '=', 'offers.id')
                ->where('invites.host_user_id', $authUser->id)
                ->select('invites.*', 'offers.title as offerTitle', 'offers.description as offerDescription', 'offers.valid_until as offerValidUntil', 'offers.created_at as offerCreatedAt')
            ;
            if($filter === 'unclaimed') {
                $invites = $invites->where('invites.status', '<>' , Invite::STATUS_CLAIMED);
            }else{
                $invites = $invites->where('offers.active', 1);
                if($filter === 'received') {
                    $invites = $invites->where('invites.status', '<' , Invite::STATUS_CLOSED);
                } elseif($filter === 'accepted') {
                    $invites = $invites->where('invites.status', Invite::STATUS_ACCEPTED);
                } elseif($filter === 'denied') {
                    $invites = $invites->where('invites.status', Invite::STATUS_DENIED);
                } elseif($filter === 'claimed') {
                    $invites = $invites->where('invites.status', Invite::STATUS_CLAIMED);
                } elseif($filter === 'closed') {
                    $invites = $invites->where('invites.status', Invite::STATUS_CLOSED);
                }
            }

            $invites = $invites->get()->toArray();
            $results = array();
            foreach($invites as &$invite) {
                $invite['invitee_user_profile'] =  $this->_getUserPublic($invite['invitee_user_id']);
                $invite['remain_time'] = $invite['offerValidUntil']*60 + strtotime($invite['offerCreatedAt']) - time();
                if($invite['remain_time'] <= 0){
                    Offer::where('id', $invite['offer_id'])->update(array('active' => 0));
                    $invite['remain_time'] = 0;
                }
                if($invite['remain_time'] > 0 || $filter=='unclaimed'){
                    $results[] = $invite;
                }
            }
            $this->data['error'] = false;
            $this->data['invites'] = $results;
            $this->data['messages'] = [];
        } else {
            $this->data['error'] = true;
            $this->data['messages'] = [ "User is not authorized." ];
        }
        $this->_JsonOutput();
    }
    
    /**
     * @desc for users only: accept/deny an offer/invitation
     * POST /acceptinvite
     * @param invite_id integer required
     * @param accept 0/1
     */
    public function postAcceptinvite() {
        //check token
        $authUser = $this->_getCurrentUser();
        if($authUser !== false && $authUser->isContact()) {
            $rules = array(
                'invite_id' => 'required',
                'accept' => 'required'
            );
            $validator = Validator::make(Input::all(), $rules);
            if ($validator->fails()) {
                $this->data = array(
                    'error' => true,
                    'messages' => $validator->messages()->all(),
                );      
            } else {
                $invite = Invite::where('id', Input::get('invite_id'))->first();
                if($invite->invitee_user_id === $authUser->id) {
                    if(Input::get('accept') == 1) {
                        $invite->status = Invite::STATUS_ACCEPTED; //accept
                        //Send push notification to the inviting host
                        
                        $sender = User::where('id', $invite->host_user_id)->first();
                        $push_type = "3";
                        if($sender->isVenue()) {
                            $push_type = "6";
                        } 


                        $result = Notification::sendNotification(
                            $invite->invitee_user_id,
                            $invite->host_user_id,
                            IoToken::getUserToken($invite->host_user_id),
                            [
                                'title'=>'You invite has been accepted',
                                'body'=> Contact::getFullName($authUser->id) .' has accepted your invite.',
                                'custom'=> [
                                    "push_type" => $push_type,
                                    'invite_id'=>$invite->id,
                                    'invitee_user_id'=>$invite->invitee_user_id,
                                    'offer_id'=>$invite->offer_id
                                ]
                            ]
                        );
                    } else {
                        $invite->status = Invite::STATUS_DENIED; //deny
                    }
                    $invite->accepted_at = date('Y-m-d H:i:s');
                    $invite->save();
                    $this->data = array(
                        'error' => false,
                        'invite' => $invite->toArray(),
                        'messages' => 'Invite accepted',
                    );
                } else {
                    $this->data['error'] = true;
                    $this->data['messages'] = ['Invalid activity detected'];
                }
            }
        } else {
            $this->data['error'] = true;
            $this->data['messages'] = [ "User is not authorized." ];
        }
        $this->_JsonOutput();
    }
    
    /**
     * @desc for users: mark claim for an offer
     *      for venues: mark an offer invite as closed
     * POST /claimoffer
     * @param invite_id
     * @param voucher
     */
    public function postClaimoffer() {
        //check token
        $authUser = $this->_getCurrentUser();
        if($authUser !== false) {
            $rules = array(
                'voucher' => 'required',
                'invite_id' => 'required',
            );
            $validator = Validator::make(Input::all(), $rules);
            if ($validator->fails()) {
                $this->data = array(
                    'error' => true,
                    'messages' => $validator->messages()->all(),
                );      
            } else {
                if($authUser->isVenue()) {
                    $invite = Invite::where('voucher', Input::get('voucher'))
                        ->where('host_user_id', $authUser->id)
                        ->where('status', Invite::STATUS_CLAIMED)
                        ->first();
                } elseif($authUser->isContact()) {
                    $invite = Invite::where('voucher', Input::get('voucher'))
                        ->where('invitee_user_id', $authUser->id)
                        ->where('status', Invite::STATUS_ACCEPTED)
                        ->first();
                }
                if($invite) {
                    if($authUser->isVenue()) {
                        $invite->status = Invite::STATUS_CLOSED;
                    } elseif($authUser->isContact()) {
                        $invite->status = Invite::STATUS_CLAIMED;
                        $invite->claimed_at = date('Y-m-d H:i:s');
                        
                        //Send push notification to the venue
                        $result = Notification::sendNotification(
                            $invite->invitee_user_id,
                            $invite->host_user_id,
                            IoToken::getUserToken($invite->host_user_id),
                            [
                                'title'=>'Your offer has been claimed',
                                'body'=> Contact::getFullName($invite->invitee_user_id) .' has claimed an offer',
                                'custom'=> [
                                    'invite_id'=>$invite->id,
                                    'offer_id'=>$invite->offer_id,
                                    'voucher'=>$invite->voucher
                                ]
                            ]
                        );
                    }
                    $invite->save();
                    $this->data['error'] = false;
                    $this->data['invite'] = $invite->toArray();
                    $this->data['messages'] = [ "Offer has been claimed" ];
                } else {
                    $this->data['error'] = true;
                    $this->data['messages'] = [ "No voucher" ];
                }
            }
        } else {
            $this->data['error'] = true;
            $this->data['messages'] = [ "User is not authorized." ];
        }
        $this->_JsonOutput();
    }
    
    /**
     * @desc delete an acount and it's related records/history
     * POST /deleteaccount
     */
    public function postDeleteaccount(){
        //check token
        $authUser = $this->_getCurrentUser();
        if($authUser !== false && $authUser->active == 0) {
            //delete profile
            if($authUser->isContact()) {
                Contact::where('user_id', $authUser->id)->delete();
            } elseif($authUser->isVenue()) {
                Venue::where('user_id', $authUser->id)->delete();
                //delete offers
                Offer::where('user_id', $authUser->id)->delete();
            }
            //delete preference
            Preference::where('user_id', $authUser->id)->delete();
            //delete messages
            Message::where('sender', $authUser->id)
                ->orWhere('receiver', $authUser->id)->delete();
            //delete locations
            UserLocation::where('user_id', $authUser->id)->delete();
            //delete friends
            Friend::where('user_a_id', $authUser->id)
                ->orWhere('user_b_id', $authUser->id)->delete();
            //delete invites
            Invite::where('host_user_id', $authUser->id)
                ->orWhere('invitee_user_id', $authUser->id)->delete();
            //
            User::where('id', $authUser->id)->delete();
            $this->data['error'] = false;
            $this->data['messages'] = [ "User account has been deleted." ];
        } else {
            $this->data['error'] = true;
            $this->data['messages'] = [ "User is not authorized." ];
        }
        $this->_JsonOutput();
    }
    
    /**
     * @desc update user preference
     * POST /XYZ
     */
    public function postXYZ() {
        //check token
        $authUser = $this->_getCurrentUser();
        if($authUser !== false) {
            
        } else {
            $this->data['error'] = true;
            $this->data['messages'] = [ "User is not authorized." ];
        }
        $this->_JsonOutput();
    }
    
    //This is only for testing purpose
    public function getTestpush(){
        //check token
        $authUser = $this->_getCurrentUser();
        if($authUser !== false) {
            $token = IoToken::getUserToken(Input::get('receiver_user_id', ''));
            $result = Notification::sendNotification(
                $authUser->id,
                Input::get('receiver_user_id', ''),
                $token,
                [
                    'title'=>'test message',
                    'body'=>'This is a test message',
                    'custom'=>'custom payload test'
                ]
            );
            $this->data['error'] = false;
            $this->data['result'] = $result;
        } else {
            $this->data['error'] = true;
            $this->data['messages'] = [ "User is not authorized." ];
        }
        $this->_JsonOutput();
    }
    
    public function getCities(){
        $cities = Cities::select('name')->get();
        $this->data['cities'] = $cities;
        $this->_JsonOutput();
    }
    
    public function getStates(){
        $states = States::select('name')->get();
        $this->data['states'] = $states;
        $this->_JsonOutput();
    }
    
    public function getGalleryImages(){
        $galleryPath = "assets/gallery";
        $paths = $this->getRecursivePaths($galleryPath);
        
        $validExtensions = array('png', 'jpg', 'jpeg');
        $current_dir = realpath('./');
        $images = array();
        foreach($paths as $name){
            $ext = pathinfo($name, PATHINFO_EXTENSION);
            if(in_array(strtolower($ext), $validExtensions)){
                $name = str_replace($current_dir, '', $name);
                $name = trim(str_replace('\\', '/', $name), '/');
                $images[] = url($name);
            }
        }
        $this->data['images'] = $images;
        $this->_JsonOutput();
    }
    
    public function postChatHistory(){
        $authUser = $this->_getCurrentUser();
        if($authUser !== false) {
            
            $rules = array(
                'friend_id' => 'required',
            );
            
            $validator = Validator::make(Input::all(), $rules);
            if ($validator->fails()) {
                $this->data = array(
                    'error' => true,
                    'messages' => $validator->messages()->all(),
                );      
            } else {
                $messageObj = Message::whereRaw(
                    "(messages.sender='".$authUser->id."' and messages.receiver='".Input::get('friend_id')."') or (messages.receiver='".$authUser->id."' and messages.sender='".Input::get('friend_id')."')"
                );

                $last_message_id = Input::get('last_message_id', false);
                
                if($last_message_id){
                    $lastMessage = Message::where('id', $last_message_id)->first();
                    if($lastMessage){
                        $messageObj = $messageObj->where('messages.sent_at', '<=', $lastMessage->sent_at)
                            ->where('messages.id', '<>', $last_message_id)
                        ;
                    }
                    
//                    Message::where('sender', $authUser->id)
//                        ->orWhere('receiver', Input::get('friend_id'))
//                        ->orderBy('sent_at', 'desc')
//                        ->limit
//                    ;
                }
                $messages = $messageObj->orderBy('messages.sent_at', 'desc')
                    ->skip(0)->take(10)
                    ->get();
                $this->data['contents'] = $messages;
            }
        } else {
            $this->data['error'] = true;
            $this->data['messages'] = [ "User is not authorized." ];
        }
        $this->_JsonOutput();
    }
    
    public function postUploadPhoto(){
        $file = Input::file('image');
        $filename = md5(time());
        $imgPath = $this->_processUploadedImage($file, '/chat_images/'.$filename.".".$file->getClientOriginalExtension());
        $imgUrl = url($imgPath);
        $this->data['link'] = $imgUrl;
        $this->_JsonOutput();
    }
    
    
}


