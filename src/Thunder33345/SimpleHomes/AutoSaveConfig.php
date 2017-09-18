<?php
declare(strict_types=true);
/** Created By Thunder33345 **/
namespace Thunder33345\SimpleHomes;

use pocketmine\utils\Config;

class AutoSaveConfig extends Config
{
  public function set($k,$v = true,$autoSave = true)
  {
    parent::set($k,$v);
    if($autoSave) parent::save();
  }
  public function remove($k,$autoSave = true)
  {
    parent::remove($k);
    if($autoSave) parent::save();
  }
  public function setNested($key,$value,$autoSave = true)
  {
    parent::setNested($key,$value);
    if($autoSave) parent::save();
  }
}