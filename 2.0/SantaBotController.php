<?php
/**
 * Created by PhpStorm.
 * User: andreyselikov
 * Date: 28.11.2020
 * Time: 13:17
 */

namespace app\controllers;

use Yii;
use yii\web\Controller;

class SantaBotController extends Controller
{
    const BOTTOKEN    = '1437468410:AAG5qCp57opoQczctB9UVFgeB8Cyshwa___';
    const PARTYNAME   = 'Christmas051220';
    const ADMINCHATID = 345514419;
    const DB_BUFFER   = 1;
    public $enableCsrfValidation;

    /** @var Redis $redis */
    private $redis;

    public function beforeAction($action)
    {
        $this->enableCsrfValidation = false;
        $this->redis = Yii::$app->redis;
        $this->redis->select(self::DB_BUFFER);
        return parent::beforeAction($action);
    }

    public function actionIndex()
    {
        $updateData = json_decode(file_get_contents('php://input'), true);
        if (isset($updateData['message'])) {
            $chatId = $updateData['message']['chat']['id'];
        } else {
            return;
        }
        $value = $this->getFromBuffer(implode(':', ['TG', $chatId, $updateData['update_id']]));
        if ($value) {
            return;
        }
        $this->saveToBuffer(implode(':', ['TG', $chatId, $updateData['update_id']]), 1);
        $this->handleCommand($updateData);
    }

    private function getFromBuffer($key)
    {
        if ($value = $this->redis->get($key)) {
            return $value;
        }
        return false;
    }

    private function saveToBuffer($key, $value)
    {
        $this->redis->set($key, $value);
    }

    private function saveToList($key, $array)
    {
        $this->redis->rpush($key, $array);
    }

    private function getList($key)
    {
        return $this->redis->lrange($key,  0, -1);
    }

    private function countInitUsers()
    {
        return $this->getFromBuffer('TGBot:'.self::PARTYNAME.':'.'count');
    }

    private function countMembers()
    {
        return $this->redis->llen('TGBot:'.self::PARTYNAME.':'.'members');
    }

    private function findUser($chatId)
    {
        $list = $this->getList('TGBot:'.self::PARTYNAME.':'.'members');
        foreach ($list??[] as $str) {
            $item = json_decode($str, 1);
            if ($item['id'] == $chatId) {
                return true;
            }
        }
        return false;
    }

    private function saveInitCount($value)
    {
        $this->saveToBuffer('TGBot:'.self::PARTYNAME.':'.'count', $value);
    }

    private function saveSantaResult($chatId, $data)
    {
        $this->saveToList('TGBot:'.self::PARTYNAME.':santa:'.$chatId, json_encode($data));
        return true;
    }

    private function findSantaResult($chatId)
    {
        $list = $this->getList('TGBot:' . self::PARTYNAME . ':santa:' . $chatId);
        $str = array_pop($list);
        return json_decode($str, 1);
    }

    private function findWish($chatId)
    {
        return $this->getFromBuffer('TGBot:'.self::PARTYNAME.':'.$chatId);
    }

    private function saveUser($chatId, $data)
    {
        if ($this->findUser($chatId)) {
            return false;
        }
        $this->saveToList('TGBot:'.self::PARTYNAME.':'.'members', json_encode($data));
        return true;
    }

    private function saveWishList($chatId, $text)
    {
        $this->saveToBuffer('TGBot:'.self::PARTYNAME.':'.$chatId, json_encode($text));
    }

    private function handleCommand($updateData)
    {
        $chatId    = false;
        $chatTitle = 'SantaBot';
        $text      = false;
        if (isset($updateData['message']) && isset($updateData['message']['text'])) {

            $chatId    = $updateData['message']['chat']['id'];

            $list      = [];
            $list[]    = $updateData['message']['from']['first_name'] ?? '';
            $list[]    = $updateData['message']['from']['last_name'] ?? '';
            $list[]    = $updateData['message']['chat']['title'] ?? '';
            $chatTitle = implode(' ', $list);

            $text      = $updateData['message']['text'];

        }

        if ($chatId && !empty($text)) {
            $this->parseCommand($chatId, $text, $chatTitle);
        }
    }

    private function parseCommand($chatId, $text, $chatTitle)
    {
        $params  = explode(' ', $text);
        $command = array_shift($params);
        switch ($command) {
            case '/help':
                $this->commandHelp($chatId, $params, $chatTitle);
                break;
            case '/start':
                $this->commandStart($chatId, $params, $chatTitle);
                break;
            case '/gosanta':
                $this->commandSanta($chatId, $params, $chatTitle);
                break;
            case '/init':
                $this->commandInit($chatId, $params, $chatTitle);
                break;
            default:
                //Если текст начался с слэша "/", то шлем сообщение
                if ($command[0] == '/') {
                    $this->sendMessage($chatId, Yii::t('telegram', 'The command is not supported!'));
                } else {
                    if ($this->findUser($chatId)) {
                        $this->saveWishList($chatId, $text);
                        return $this->sendMessage(
                            $chatId, 'Your wishes are saved, if you write again, the data will be overwritten'
                        );
                    } else {
                        $this->sendMessage($chatId, 'Type the command /start');
                    }
                }
        }
    }

    private function commandHelp($chatId, $params, $chatTitle)
    {
        return $this->sendMessage(
            $chatId, '/start - the main user team, we add you and wait for everyone to register. 
            When everyone is registered, this command will return you the Name of the one who got the gift'
        );
    }

    private function commandSanta($chatId, $params, $chatTitle)
    {
        if ($this->countMembers() < $this->countInitUsers()) {
            return $this->sendMessage(
                $chatId,
                'Еще не все, ждем!'
            );
        } elseif ($this->countMembers() == $this->countInitUsers()) {

            $array_new = $array_users = $array_chats = [];
            $list = $this->getList('TGBot:'.self::PARTYNAME.':'.'members');
            foreach ($list as $str) {
                $item = json_decode($str, 1);
                $array_chats[] = $item['id'];
                $array_users[] = [
                    'id'   => $item['id'],
                    'name' => $item['title'],
                ];
            }
            $newKeys = $this->arrayShuffle(0,count($array_chats)-1);
            foreach ($array_chats as $k=>$id) {

                $array_new[$id] = $array_users[$newKeys[$k]];
                $this->saveSantaResult($array_chats[$k],  $array_new[$id]);
                $this->sendMessage($array_chats[$k], 'The drawing is over! type the command /start');

            }
            //$this->sendAdminMessage(print_r($array_new, 1));

        } else {
            return $this->sendAdminMessage('For some reason, there are more of them! What to do?');
        }
    }

    function arrayShuffle($min, $max)
    {
        do {
            $try     = true;
            $numbers = range($min, $max);
            shuffle($numbers);
            foreach ($numbers as $key => $number) {
                if ($key == $number) $try = false;
            }
        } while ($try == false);

        return $numbers;
    }

    private function commandStart($chatId, $params, $chatTitle)
    {
        $saved = $this->saveUser($chatId, [
            'id' => $chatId,
            'title' => $chatTitle
        ]);
        if ($saved) {

            if ($this->countMembers() > $this->countInitUsers()) {

                return $this->sendAdminMessage('For some reason, there are more of them! What to do?');

            } elseif ($this->countMembers() == $this->countInitUsers()) {

                return $this->sendAdminMessage('That\'s it, it\'s time to start!');

            }
            return $this->sendMessage(
                $chatId,
                'New user saved. Now there are ' .
                $this->countMembers() . ' of ' . $this->countInitUsers() .'. '.
                'Now you can add wishes in free form:'
            );

        } else {

            if ($this->countMembers() < $this->countInitUsers()) {

                return $this->sendMessage(
                    $chatId, 'User exist, waiting for others ('.($this->countInitUsers() - $this->countMembers()).')'
                );

            } elseif ($this->countMembers() == $this->countInitUsers()) {

                $santaJob = $this->findSantaResult($chatId);
                $wish = $this->findWish($santaJob['id']);
                $wish = json_decode($wish);

                return $this->sendMessage(
                    $chatId, 'You give a gift to ' . $santaJob['name']. ' and this is what he/she wished for: ' . $wish
                );
            }
        }
    }

    private function sendAdminMessage($text)
    {
        return $this->sendMessage(
            self::ADMINCHATID,
            $text
        );
    }

    private function commandInit($chatId, $params, $chatTitle)
    {
        if (isset($params[0]) && is_numeric($params[0])) {
            $this->saveInitCount($params[0]);
            return $this->sendMessage(
                $chatId,
                "Hi {$chatTitle}, saved - {$params[0]}"
            );
        } else {
            return $this->sendMessage(
                $chatId,
                "Hi {$chatTitle}, \"".print_r($params,1)."\" whats you want?"
            );
        }

    }

    private function sendMessage($chatId, $text)
    {
        echo $chatId. $text;return;
        $ch = curl_init("https://api.telegram.org/bot".self::BOTTOKEN."/sendMessage");
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'chat_id'=>$chatId,
            'text'=>$text,
        ]));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_exec($ch);
        curl_close($ch);

        return true;
    }

}