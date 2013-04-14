<?php
/**
 * This class is a backend to use the ownCloud database while running Dokuwiki
 * You can have a look to <ownCloud-Path>/lib/db.php for functions provided.
 * Here is a description for developer to using the ownCloud database
 * http://doc.owncloud.org/server/5.0/developer_manual/app/appframework/database.html
 * 
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Martin Schulte <lebowski[at]corvus[dot]uberspace[dot]de>
 */
// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

//constants
if (!defined('DOKU_LF')) define('DOKU_LF', "\n");
if (!defined('DOKU_TAB')) define('DOKU_TAB', "\t");
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC.'lib/plugins/');

error_reporting (E_ALL | E_STRICT);  
ini_set ('display_errors', 'On');


class helper_plugin_owncloud extends DokuWiki_Plugin 
{
	// The last query of type PDOStatementWrapper (see <ownCloud-Path>/lib/db.php)
	protected $lastQuery;
    
    /**
     * Constructor to connect to db and check if ownCloud is installed 
     */
    public function helper_plugin_owncloud() {
		require_once($this->getConf('pathtoowncloud').'/lib/base.php');
		// Check if ownCloud is installed or in maintenance (update) mode
		if (!OC_Config::getValue('installed', false)) {
			global $conf;
			require_once('lang/'.$conf['lang'].'/settings.php');
			echo $lang['owncloudNotInstalled'];
			exit();
		}		
    }
    
    /**
    * Prepares and executes a SQL query
    *
    * @param $sql String The SQL-Query (for syntax see http://doc.owncloud.org/server/5.0/developer_manual/app/appframework/database.html)
    * @param $params array The parameters should be used in the sql-query
    */
    public function dbQuery($sql, $params){
		$db = \OC_DB::prepare($sql);
		$this->lastQuery = $db->execute($params);
				
	}
	
	
	// Returns the last query (last call of dbQuery)
	public function getLastQuery(){
		return $this->lastQuery;
	}
	
	/**
    * Returns the path for the correspondig fileID (from table OC_filecache)
    *
    * @param $id integer The fileID
    * @param $wikiID bool If true, slashes (/) will be replaced by colons (:)
    */
	public function getFilenameForID($id, $wikiID=false){
		$this->dbQuery('SELECT `path` FROM `*PREFIX*filecache` WHERE fileid = ?', array($id));
		$path = $this->lastQuery->fetchOne();
		if($wikiID) return str_replace('/',':',$path);
		return $path;
	}
	
	/**
    * Returns the fileid for the correspondig path (from table OC_filecache)
    *
    * @param $file string The path
    */
	public function getIDForFilename($file){
		$this->dbQuery('SELECT `fileid` FROM `*PREFIX*filecache` WHERE path = ?', array($file));
		$id = $this->lastQuery->fetchOne();
		return $id;
	}
          
    
   
}
