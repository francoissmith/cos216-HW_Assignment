<?php

include "database.php";
session_start();

class Database
{
    public static function instance()
    {
        static $instance = null;
        if ($instance == null)
            $instance = new Database();
        return $instance;
    }

    // DB Parameters
    private $DB_SERVERNAME = "wheatley.cs.up.ac.za";
    private $DB_USERNAME = "u19314486";
    private $DB_PASSWORD = "6CH4JBHVIR24O5J7ZGWPRUMZZJAPLFZO";
    private $DB_NAME = "u19314486";
    private $db_conn;

    private function __construct()
    {
        $this->connect();
    }

    public function query($qry) {
        return $this->db_conn->query($qry);
    }

    public function prepare($qry) {
        return $this->db_conn->prepare($qry);
    }

    private function __destruct()
    {
        $this->db_conn = null;
    }

    // connect to DB
    public function connect()
    {
        $this->db_conn = null;
        try {
            $this->db_conn = new mysqli($this->DB_SERVERNAME, $this->DB_USERNAME, $this->DB_PASSWORD, $this->DB_NAME);
            return true;
        } catch (PDOException $e) {
            echo ('Connection Error: ' . $e->getMessage());
            return false;
        }
    }

    public function getUserData($email, $password) {
        $q = "SELECT * FROM Users WHERE email='" . $email . "'";
        $user_data = $this->db_conn->query($q)->fetch_object();

        if (password_verify($password, $user_data->password)) {
            $user_data = json_encode($user_data);
            return $user_data;
        }

        return false;
    }

    public function confirmAPIKey($api_key)
    {
        if (strlen($api_key) == 32) {
            $q = "SELECT * FROM Users WHERE api_key='" . $api_key . "'";
            $existingAPIKey = $this->db_conn->query($q);
    
            if ($existingAPIKey->num_rows > 0)
                return true;
        }

        return false;
    }
}

$myAPI = new myAPI();

if (strcasecmp($_SERVER['REQUEST_METHOD'], 'POST') != 0) {
    $myAPI->internal_error("Request method must be POST!");
}

$mimeType = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';
if (strcasecmp($mimeType, 'application/json') != 0) {
    $myAPI->internal_error('Content-Type should be of type: application/json');
}

$data = trim(file_get_contents("php://input"));

$dec_data = json_decode($data, true);

if (!is_array($dec_data)) {
    $myAPI->internal_error('Invalid JSON in content!');
}

if (Database::instance()->connect() == false)
    $myAPI->internal_error("No connection to DB / Multiple instaces");

if ($dec_data["type"] != "info" && $dec_data["type"] != "update" && $dec_data["type"] != "login" && $dec_data["type"] != "rate" && $dec_data["type"] != "logout" && $dec_data["type"] == "chat")
    $myAPI->internal_error("API type " . $dec_data["type"] . " does not exist");

// ======================================================================================================

if ($dec_data["type"] == "login" || $dec_data["type"] == "logout") {
    if(!isset($dec_data["email"]) || !isset($dec_data["password"]))
    {
        $myAPI->internal_error("0: One or more fields empty");
        die();
    }

    //Retrieve submitted data
    $email = $dec_data["email"];
    $password = $dec_data["password"];

    //Sanitizing email address
    $email = filter_var($email, FILTER_SANITIZE_EMAIL);
    $password = filter_var($password, FILTER_SANITIZE_STRING);

    //REVALIDATING user input SERVERSIDE

    $emailRegEx = '/^(?!(?:(?:\\x22?\\x5C[\\x00-\\x7E]\\x22?)|(?:\\x22?[^\\x5C\\x22]\\x22?)){255,})(?!(?:(?:\\x22?\\x5C[\\x00-\\x7E]\\x22?)|(?:\\x22?[^\\x5C\\x22]\\x22?)){65,}@)(?:(?:[\\x21\\x23-\\x27\\x2A\\x2B\\x2D\\x2F-\\x39\\x3D\\x3F\\x5E-\\x7E]+)|(?:\\x22(?:[\\x01-\\x08\\x0B\\x0C\\x0E-\\x1F\\x21\\x23-\\x5B\\x5D-\\x7F]|(?:\\x5C[\\x00-\\x7F]))*\\x22))(?:\\.(?:(?:[\\x21\\x23-\\x27\\x2A\\x2B\\x2D\\x2F-\\x39\\x3D\\x3F\\x5E-\\x7E]+)|(?:\\x22(?:[\\x01-\\x08\\x0B\\x0C\\x0E-\\x1F\\x21\\x23-\\x5B\\x5D-\\x7F]|(?:\\x5C[\\x00-\\x7F]))*\\x22)))*@(?:(?:(?!.*[^.]{64,})(?:(?:(?:xn--)?[a-z0-9]+(?:-+[a-z0-9]+)*\\.){1,126}){1,}(?:(?:[a-z][a-z0-9]*)|(?:(?:xn--)[a-z0-9]+))(?:-+[a-z0-9]+)*)|(?:\\[(?:(?:IPv6:(?:(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){7})|(?:(?!(?:.*[a-f0-9][:\\]]){7,})(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,5})?::(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,5})?)))|(?:(?:IPv6:(?:(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){5}:)|(?:(?!(?:.*[a-f0-9]:){5,})(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,3})?::(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,3}:)?)))?(?:(?:25[0-5])|(?:2[0-4][0-9])|(?:1[0-9]{2})|(?:[1-9]?[0-9]))(?:\\.(?:(?:25[0-5])|(?:2[0-4][0-9])|(?:1[0-9]{2})|(?:[1-9]?[0-9]))){3}))\\]))$/iD';

    if(!preg_match($emailRegEx, $email)){
        $myAPI->internal_error("1: Invalid email");
        die();
    }

    //Get SALT for corresponding user in database
    $result = Database::instance()->query("SELECT * FROM Users WHERE email='$email'");

    //Email not in DB (not registered)
    if (! $result->rowCount() > 0) {
        $myAPI->internal_error("2: Email does not exist in Database");
        die();
    }

    $row = $result->fetch();

    $uSalt = $row["uSalt"];

    $options = array(
        'salt' => $uSalt
    );
    //PASSWORD_DEFAULT ensures the latest, most secure algorithm is used (currently PASSWORD_BCRYPT)
    $password_hash = password_hash($password, PASSWORD_DEFAULT, $options);

    //Compare password hash with that stored in DB
    $passwordDB = $row["password"];

    if($passwordDB != $password_hash){
        $myAPI->internal_error("3: Incorrect password");
        die();
    }

    //Made it till here! Congratz! Login by returning APIKEY
    $myAPI->success($row["api_key"]);

    die();

     if(isset($dec_data["email"])) {
        if ($dec_data["email"] != "")
            $email = $dec_data["email"];
        else
            $email = false;
    }

    if(isset($dec_data["password"])) {
        if ($dec_data["password"] != "")
            $password = $dec_data["password"];
        else
            $password = false;
    }    

    if ($dec_data["type"] == "logout") 
        $myAPI->logoutRequest($email, $password);
    else
        $myAPI->loginRequest($email, $password);

// ======================================================================================================

} else if ($dec_data["type"] == "info") {
    if (isset($dec_data["key"]))
        $api_key = $dec_data["key"];

    if (Database::instance()->confirmAPIKey($api_key) != true) {
        $myAPI->internal_error("Invalid API Key");
    }
    if (isset($dec_data["title"])) {
        if ($dec_data["title"] != "")
            $title = $dec_data["title"];
    } else {
        $title = false;
    }
    
    if (isset($dec_data["author"])) {
        if ($dec_data["author"] != "")
            $author = $dec_data["author"];
    } else {
        $author = false;
    }
    
    if (isset($dec_data["date"])) {
        if ($dec_data["date"] != "")
            $date = $dec_data["date"];
    } else {
        $date = false;
    }
    
    if (isset($dec_data["country"])) {
        if ($dec_data["country"] != "")
            $country = $dec_data["country"];
    } else {
        $country = false;
    }
    
    if (isset($dec_data["return"])) {
        if ($dec_data["return"] != "")
            $return = $dec_data["return"];
    } else {
        $return = false;
    }
    
        $myAPI->readRequest($title, $author, $date, $country, $return);

// ======================================================================================================
        
} else if($dec_data["type"] == "chat"){
            
    if(isset($dec_data["id"])){
         $id = $dec_data["id"];
     } else {$myAPI->internal_error("You need to provide an id to request Users."); }
    
    $result = Database::instance()->query("SELECT * FROM Users WHERE api_key='$api_key' AND id='$id'");
    
    if(isset($dec_data["saveChat"])){
         $saveChat = $dec_data["saveChat"];
       
        if($result->rowCount() > 0){  
                $stmt = Database::instance()->prepareQuery("UPDATE Users SET chat=? WHERE api_key=? AND id=?");
                $res = $stmt->execute([$saveChat, $api_key, $id]);
                if(! $res){
                    $myAPI->internal_error("Error: failed to write to DB");
                } else {  $myAPI->success("Chat saved for Client #$id"); }
        } else {  
                $stmt = Database::instance()->prepareQuery("INSERT INTO Users (id, api_key, chat) VALUES(?,?,?)");
                $res = $stmt->execute([$id, $api_key, $saveChat]); 
                 if(! $res){
                    $myAPI->internal_error("Error: failed to write to DB");
                } else { $myAPI->success("Chat updated for Client #$id");  }
        }
    } else { 
            $chat = $result->fetch()["chat"];
            if($chat == null)
                $myAPI->internal_error("No chat for id $id");
        
            
           $myAPI->success($chat);
    }
 
    die();
}




// =============================================================================================================================================================
class myAPI
{

    public function __construct()
    {
        header("Content-Type: application/json"); // Only return JSON
    }
    // ************************************************************************************************************
    public function loginRequest($email, $password) {
        if ($email == false || $password == false) {
            $this->internal_error("Invalid email or password.");
        } else {
            $json = Database::instance()->getUserData($email, $password);

            if ($json == false) {
                $this->internal_error("Invalid email or password.");
            } else {
                $data = json_decode($json);
    
                if($_SESSION["loggedin"] == true) echo "Already logged in";
                else {
                    $_SESSION["loggedin"] = true;
                    $_SESSION["user"] = $data->name;
                    $_SESSION["api_key"] = $data->api_key;  

                    $this->loginResponse($data);
                }
            }
       }
    }
    // ************************************************************************************************************
    public function logoutRequest($email, $password)
    {
        if ($email == false || $password == false) {
            $this->internal_error("Invalid email or password.");
        } else {
            $json = Database::instance()->getUserData($email, $password);

            if ($json == false) {
                $this->internal_error("Invalid password.");
            } else {
                $data = json_decode($json);

                session_start();
                if (isset($_SESSION["loggedin"])) {
                    session_unset();
                    session_destroy();
                    echo "Logged out";
                } else
                    echo "Not logged in";

                // $this->loginResponse($data);
            }
        }
    }
    // ************************************************************************************************************
    public function loginResponse($data)
    {
        $DATE = new DateTime();
        $result = array(
            'status' => 'success',
            'timestamp' => $DATE->getTimestamp(),
            'data' => array()
        );

        foreach ($data as $field) {
            if ($field == true) {
                array_push($result["data"], $field);
            }
        }

        if (empty($result["data"])) {
            array_push($result["data"], "Nothing found");
        }

        $result = json_encode($result);
        echo $result;
    }
    
    // ************************************************************************************************************
    public function readRequest($title, $author, $date, $country, $return)
    {
        if ($return == false) {
            $internal_error = true;
        } else {
            $internal_error = false;

            if ($author != false) {
                $res = $this->cURL_RAP("https://google-news.p.rapidapi.com/v1/search?q=" . urlencode($author) . "&lang=en&page_size=20");
            } else if ($date != false) {
                $res = $this->cURL_MED_byDate($date);
            } else if ($country != false) {
                $res = $this->cURL_MED_byCountry($country);
            } else if ($title != false) {
                $res = $this->cURL_RAP("https://free-news.p.rapidapi.com/v1/search?q=" . urlencode($title) . "&lang=en&page_size=20");
            } else {
                $internal_error = true;
                $res = false;
            }
        }

        $this->createResponse($res, $internal_error, $return);
    }
    // ************************************************************************************************************
    public function createResponse($res, $internal_error, $return)
    {
        $DATE = new DateTime();
        if ($internal_error == true) {
            $result = array(
                'status' => 'failed',
                'timestamp' => $DATE->getTimestamp(),
                'data' => array('message' => 'Internal Error')
            );
        } else if ($res == false) {
            $result = array(
                'status' => 'error',
                'timestamp' => $DATE->getTimestamp(),
                'data' => array('message' => 'External Error')
            );
        } else {
            $result = array(
                'status' => 'success',
                'timestamp' => $DATE->getTimestamp(),
                'data' => array()
            );

            foreach ($res as $obj_res) {
                $item = $this->proc_Return($obj_res, $return);
                if ($item == true) {
                    array_push($result["data"], $item);
                }
            }

            if (empty($result["data"])) {
                array_push($result["data"], "Nothing found");
            }
        }

        $result = json_encode($result);
        echo $result;
    }
    // ************************************************************************************************************
    public function proc_Return($obj_res, $return)
    {
        // $obj_res = json_decode($obj_res);

        $article = array();
        $std_obj = array();

        $std_obj = $this->get_StdObj($obj_res, $std_obj);

        if (isset($obj_res->title) || isset($obj_res->author)) {
            foreach ($return as $obj) {
                switch ($obj) {
                    case 'title':
                        $article += array("title" => $std_obj["title"]);
                        break;
                    case 'author':
                        $article += array("author" => $std_obj["author"]);
                        break;
                    case 'date':
                        $article += array("published_date" => $std_obj["date"]);
                        break;
                    case 'rating':
                        $article += array("rating" => 0);
                        break;
                    case 'category':
                        $article += array("category" => $std_obj["category"]);
                        break;
                    case 'image':
                        $article += array("image" => $std_obj["image"]);
                        break;
                    case 'description':
                        $article += array("description" => $std_obj["description"]);
                        break;
                    case 'url':
                        $article += array("url" => $std_obj["url"]);
                    case 'source':
                        $article += array("source" => $std_obj["source"]);
                        break;
                }
            }
        }
        return $article;
    }
    // ************************************************************************************************************
    private function get_stdObj($obj_res, $std_obj)
    {
        if (isset($obj_res->title)) {
            $std_obj += array("title" => $obj_res->title);
        }

        if (isset($obj_res->author)) {
            $std_obj += array("author" => $obj_res->author);
        }

        if (isset($obj_res->published_date)) {
            $std_obj += array("date" => $obj_res->published_date);
        }

        if (isset($obj_res->publishedAt)) {
            $std_obj += array("date" => $obj_res->publishedAt);
        }

        if (isset($obj_res->topic)) {
            $std_obj += array("category" => $obj_res->topic);
        }

        if (isset($obj_res->link)) {
            $std_obj += array("url" => $obj_res->link);
        }

        if (isset($obj_res->url)) {
            $std_obj += array("url" => $obj_res->url);
        }

        if (isset($obj_res->summary)) {
            $std_obj += array("description" => $obj_res->summary);
        }

        if (isset($obj_res->description)) {
            $std_obj += array("description" => $obj_res->description);
        }

        if (isset($obj_res->country)) {
            $std_obj += array("country" => $obj_res->country);
        }

        if (isset($obj_res->rights)) {
            $std_obj += array("source" => $obj_res->rights);
        }

        if (isset($obj_res->source)) {
            $std_obj += array("source" => $obj_res->source->name);
        }

        if (isset($obj_res->urlToImage)) {
            $std_obj += array("image" => $obj_res->urlToImage);
        }

        if (isset($obj_res->media)) {
            $std_obj += array("image" => $obj_res->media);
        }

        return $std_obj;
    }
    // ************************************************************************************************************
    public function internal_error($err)
    {
        $DATE = new DateTime();
        $result = array(
            'status' => 'failed',
            'timestamp' => $DATE->getTimestamp(),
            'data' => array('message' => $err)
        );
        $result = json_encode($result);
        echo $result;
        die();
    }
// ************************************************************************************************************
    public function success($msg){
        $date = new DateTime();
        $response_data  = array(
            'status'    => 'success',
            'timestamp' => $date->getTimestamp(),
            'data'     => $msg
        );
        $response_data = json_encode($response_data);
        echo $response_data;
        die();
    }
    // ************************************************************************************************************
    private function cURL_RAP($url)
    {
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => "$url",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_ENCODING => "utf-8",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => [
                "X-RapidAPI-Host: free-news.p.rapidapi.com",
                "X-RapidAPI-Key: c3d67a9513msh106ee7947b9c346p1ec49fjsn204e866a2513"
            ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            echo "cURL Error #:" . $err;
            return false;
        } else {
            $response = json_decode($response);
            return $response->articles;
        }
    }
    // ************************************************************************************************************
    private function cURL_MED_byDate($date)
    {
        $queryString = http_build_query([
            'q' => '*',
            'from' => $date,
            'apiKey' => '72feb3aca9494418ba629650878f20aa',
            'sort' => 'popularity',
            'limit' => 20
        ]);

        $curl = curl_init(sprintf('%s?%s', 'https://newsapi.org/v2/everything', $queryString));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $json = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            echo "cURL Error #:" . $err;
            return false;
        } else {
            $apiResult = json_decode($json);
            return $apiResult->articles;
        }
    }
    // ************************************************************************************************************
    private function cURL_MED_byCountry($country)
    {
        $queryString = http_build_query([
            'country' => $country,
            'apiKey' => '72feb3aca9494418ba629650878f20aa',
            'sort' => 'popularity',
            'limit' => 20
        ]);

        $curl = curl_init(sprintf('%s?%s', 'https://newsapi.org/v2/top-headlines', $queryString));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $json = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            echo "cURL Error #:" . $err;
            return false;
        } else {
            $apiResult = json_decode($json);
            return $apiResult->articles;
        }
    }
}
