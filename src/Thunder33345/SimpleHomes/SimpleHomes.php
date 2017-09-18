<?php
declare(strict_types=true);
/** Created By Thunder33345 **/
namespace Thunder33345\SimpleHomes;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\level\Level;
use pocketmine\level\Location;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;

class SimpleHomes extends PluginBase implements Listener
{
  const SETHOME_RESERVED = '1';
  const SETHOME_EXIST = '2';
  const SETHOME_LOC_INVALID = '3';
  const GOTOHOME_NON_EXIST = '11';
  const QUICKHOME_NOT_SET = '21';
  const ARRAYTOLOC_LEVEL_ERR = '31';

  public function onLoad()
  {
    @mkdir($this->getDataFolder());
  }

  public function onEnable()
  {
    $this->getServer()->getPluginManager()->registerEvents($this, $this);
  }

  public function onDisable()
  {

  }

  public function onCommand(CommandSender $sender, Command $command, string $label, array $args):bool
  {
    /*
     Home file format
    [
    "config":[
    ver:"$VERSION"
    quick:"$HOMENAME"
    last:"$LASTHOMENAME"
    ]
     "$HOMENAME":[
    level:$WORLDNAME
    x:$x
    y:$y
    z:$z
     ]
    ]
     */
    if(!$sender instanceof Player){
      $sender->sendMessage('Please run this as player');
      return true;
    }
    $player = $sender;
    switch($command->getName()){
      case "managehome":
        // /managehome <set/add|unset/remove|goto|setQuick|info <home>>|<unsetQuick>|<list>|<help>
        if(count($args) < 1){
          $sender->sendMessage('/'.$label.' <set/add|unset/remove|go/goto|setQuick|info <home>>|<unsetQuick>|<list>|<help>');
          return true;
        }
        switch(strtolower($args[0])){
          case "help":
            $sender->sendMessage('Commands: /managehome(/home),/gotohome(/h),/lasthome(/lh),/quickhome(/qh)');
            $sender->sendMessage('/managehome <set/add|unset/remove|goto|setQuick|info <home>>|<unsetQuick>|<list>|<help>');
            $sender->sendMessage('home name can only contain lowercase, colour code will be removed automatically');
            $sender->sendMessage('set: set your home at current location and pitch+yaw');
            $sender->sendMessage('unset: removes your home point');
            $sender->sendMessage('goto: goto said home if possible');
            $sender->sendMessage('setquick: set a quick name home which can be accessed via /quickhome(will overwrite if there\'s any)');
            $sender->sendMessage('unsetquick: unset the quick name home');
            $sender->sendMessage('info: list info and coordinates of your home');
            $sender->sendMessage('list: list all of your home names');
            $sender->sendMessage('help: show this page');
            break;
          case "set":
          case "add":
            if(count($args) !== 2){
              $player->sendMessage('/home set <name>');
              return true;
            }
            $try = $this->setHome($player, $args[1], $player);
            if($try !== true){
              switch($try){
                case self::SETHOME_RESERVED:
                  $player->sendMessage(TextFormat::RED.'The selected name is reserved, please try something else.');
                  break;
                case self::SETHOME_EXIST:
                  $player->sendMessage(TextFormat::RED.'The selected name already exist, please unset it first.');
                  break;
                case self::SETHOME_LOC_INVALID:
                  $player->sendMessage(TextFormat::RED.'Internal error!: "Location Invalid".');
                  break;
              }
              return true;
            }
            $player->sendMessage(TextFormat::GREEN.'Home '.$args[1].' set!');
            break;
          case "unset":
          case "remove":
            if(count($args) !== 2){
              $player->sendMessage('/home unset <name>');
              return true;
            }
            $this->unsetHome($player, $args[1]);
            $player->sendMessage('Removed Home '.$args[1]);
            break;
          case "goto":
          case "go":
            if(!isset($args[1])) $args[1] = false;
            return $this->goHomeHelper($player, $args[1], '/home go');
            break;
          case "setquick":
            if(count($args) !== 2){
              $player->sendMessage('/home setquick <name>');
              return true;
            }
            $this->setQuickHome($player, $args[1]);
            $player->sendMessage('Set Quick home as :'.$args[1].' /quickhome or /qh to use it');
            break;
          case "unsetquick":
            $this->unsetQuickHome($player);
            $player->sendMessage('Quick Home has been unset');
            break;
          case "info":
            if(count($args) !== 2){
              $player->sendMessage('/home info <name>');
              return true;
            }
            $config = $this->getHomeConfig($player);
            $home = $config->get($args[1]);
            if(!is_array($home)){
              $player->sendMessage('Home not found');
              return true;
            }
            $player->sendMessage('Home '.$args[1].':');
            $player->sendMessage('World: '.$home['level']);
            $player->sendMessage('X: '.$home['x'].' Y: '.$home['y'].' Z: '.$home['z']);
            $player->sendMessage('Yaw: '.$home['yaw'].' Pitch: '.$home['pitch']);
            break;
          case "list":
            $list = $this->getHomeList($player);
            $list = implode(', ', $list);
            $player->sendMessage('Home List: '.$list);
            break;
          default:
            $sender->sendMessage('/'.$label.' <set/add|unset/remove|goto|setQuick|info <home>>|<unsetQuick>|<list>|<help>');
            break;
        }
        break;
      case "gohome":
        if(count($args) !== 1){
          $list = $this->getHomeList($player);
          $list = implode(', ', $list);
          $player->sendMessage('/gohome <home name>');
          $player->sendMessage('Home List: '.$list);
          return true;
        }
        return $this->goHomeHelper($player, $args[1], '/gohome');
        break;
      case "lasthome":
        $config = $this->getHomeConfig($player);
        $lastHome = $config->getNested('config.last');
        $this->goHomeHelper($player, $lastHome, '/lasthome');
        break;
      case "quickhome":
        $config = $this->getHomeConfig($player);
        $quickHome = $config->getNested('config.quick', null);
        if($quickHome === null){
          $player->sendMessage('Quick Home is not set');
          return true;
        }
        $this->goHomeHelper($player, $quickHome, '/quickhome');
        break;
    }
    return true;
  }

  private function goHomeHelper(Player $player, $home, $cmd)
  {
    if($home === false){
      $player->sendMessage("/$cmd <name>");
      return true;
    }
    $try = $this->gotoHome($player, $home, $player);
    if($try){
      $player->sendMessage(TextFormat::GREEN.'Teleported you to '.$home);
      $this->setLastHome($player, $home);
      return true;
    }
    switch($try){
      case self::GOTOHOME_NON_EXIST:
        $player->sendMessage(TextFormat::RED.'Home "'.$home.'" dosent exist');
        break;
      case is_array($try):
        $player->sendMessage(TextFormat::RED.'Internal error: Failed to reconstruct Location missing: '.implode(',', $try));
        break;
      case false:
        $player->sendMessage(TextFormat::RED.'Unknown error, failed to initialize teleport sequence');
        break;
    }
    return true;
  }

  public function setHome($player, string $homeName, Location $location)
  {
    $homeName = TextFormat::clean(strtolower($homeName), true);
    $config = $this->getHomeConfig($player);

    if($config->get($homeName, false) !== false) return self::SETHOME_EXIST;
    if($homeName == 'config') return self::SETHOME_RESERVED;
    if(!$location->isValid()) return self::SETHOME_LOC_INVALID;
    $config->set($homeName, $this->locationToArray($location));
    return true;
  }

  public function unsetHome($player, string $homeName)
  {
    $config = $this->getHomeConfig($player);
    $config->remove($homeName);
  }

  public function gotoHome($player, $home, Player $tpWho)
  {
    $config = $this->getHomeConfig($player);
    $homeData = $config->get($home, false);
    if($homeData === false){
      return self::GOTOHOME_NON_EXIST;
    }
    $location = $this->arrayToLocation($homeData);
    if(!$location instanceof Location OR !$location->isValid()) return $location;
    $result = $tpWho->teleport($location);
    return $result;
  }

  public function setLastHome($player, $home)
  {
    $config = $this->getHomeConfig($player);
    $config->setNested('config.last', $home);
    return true;
  }

  public function gotoLastHome($player, Player $tpWho)
  {
    $config = $this->getHomeConfig($player);
    $lastHome = $config->getNested('config.last');
    return $this->gotoHome($player, $lastHome, $tpWho);
  }

  public function setQuickHome($player, $home)
  {
    $config = $this->getHomeConfig($player);
    $config->setNested('config.quick', $home);
    return true;
  }

  public function gotoQuickHome($player, Player $tpWho)
  {
    $config = $this->getHomeConfig($player);
    $quickHome = $config->getNested('config.quick', null);
    if($quickHome === null) return self::QUICKHOME_NOT_SET;
    return $this->gotoHome($player, $quickHome, $tpWho);
  }

  public function unsetQuickHome($player)
  {
    $config = $this->getHomeConfig($player);
    $config->setNested('config.quick', null);
    return true;
  }

  public function getHomeLocation($player, $home)
  {
    $config = $this->getHomeConfig($player);
    return $location = $this->arrayToLocation($config->get($home));
  }

  public function getHomeList($player)
  {
    $config = $this->getHomeConfig($player);
    $homeList = [];
    foreach($config->getAll() as $homeName => $homeData){
      $homeList[] = $homeName;
    }
    return $homeList;
  }

  private function locationToArray(Location $location)
  {
    if(!$location->isValid()) return false;
    //@formatter:off
    return
     [
      'level' => $location->getLevel()->getFolderName(),
       'x' => round($location->getX(),10),
       'y' => round($location->getY(),10),
       'z' => round($location->getZ(),10),
       'yaw' => round($location->getYaw(),5),
       'pitch' => round($location->getPitch(),5),
    ];
    //@formatter:on
  }

  private function arrayToLocation(array $data)
  {
    $requires = ['level', 'x', 'y', 'z', 'yaw', 'pitch'];
    $missing = ['Missing required data'];

    foreach($requires as $require) if(!isset($data[$require])) $missing[] = $require;

    if(count($missing) > 0) return $missing;

    $levelName = $data['level'];
    if(!$this->getServer()->isLevelLoaded($levelName)) $this->getServer()->loadLevel($levelName);

    $level = $this->getServer()->getLevelByName($levelName);
    if(!$level instanceof Level){
      return self::ARRAYTOLOC_LEVEL_ERR;
    }

    return new Location($data['x'], $data['y'], $data['z'], $data['yaw'], $data['pitch'], $levelName);
  }

  public function getHomeConfig($player){ return new AutoSaveConfig($this->getHomeConfigLocation($this->toStr($player))); }

  private function getHomeConfigLocation($player){ return $this->getDataFolder().'homes'.DIRECTORY_SEPARATOR.$this->toStr($player).'.json'; }

  private function toStr($player){ if($player instanceof Player) return $player->getLowerCaseName();else return strtolower($player); }
}