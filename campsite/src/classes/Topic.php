<?php
/**
 * Includes
 */
require_once($GLOBALS['g_campsiteDir'].'/db_connect.php');
require_once($GLOBALS['g_campsiteDir'].'/classes/DatabaseObject.php');
require_once($GLOBALS['g_campsiteDir'].'/classes/DbObjectArray.php');
require_once($GLOBALS['g_campsiteDir'].'/classes/Log.php');

/**
 * @package Campsite
 */
class Topic extends DatabaseObject {
	var $m_keyColumnNames = array('Id');

	var $m_dbTableName = 'Topics';

	var $m_columnNames = array('Id', 'LanguageId', 'Name', 'ParentId', 'TopicOrder');

	var $m_hasSubtopics = null;

	var $m_names = array();


	/**
	 * A topic is like a category for a piece of data.
	 *
	 * @param int $p_id
	 */
	public function Topic($p_idOrName = null)
	{
		parent::DatabaseObject($this->m_columnNames);

		if (preg_match('/^[\d]+$/', $p_idOrName) > 0) {
            $this->m_data['Id'] = $p_idOrName;
            $this->fetch();
		} elseif (is_string($p_idOrName) && !empty($p_idOrName)) {
		    $topic = Topic::GetByFullName($p_idOrName);
		    if (!is_null($topic)) {
		        $this->fetch($topic->m_data);
		    }
		}
	} // constructor


	/**
	 * Fetch the topic and all its translations.
	 *
	 * @return void
	 */
	public function fetch($p_columns = null)
	{
		global $g_ado_db;
		if (!is_null($p_columns)) {
            if ($this->readFromCache($p_columns) !== false) {
                return true;
            }
			foreach ($p_columns as $columnName => $value) {
				if (in_array($columnName, $this->m_columnNames)) {
					$this->m_data[$columnName]  = $value;
				}
			}
			if (isset($p_columns['LanguageId']) && isset($p_columns['Name'])) {
				$this->m_names[$p_columns['LanguageId']] = $p_columns['Name'];
			}
			$this->m_exists = true;
		} else {
            if ($this->readFromCache() !== false) {
                return true;
            }
			$columnNames = implode(",", $this->m_columnNames);
			$queryStr = "SELECT $columnNames FROM ".$this->m_dbTableName
						." WHERE Id=".$this->m_data['Id'];
			$rows = $g_ado_db->GetAll($queryStr);
			if ($rows && (count($rows) > 0)) {
				$row = array_pop($rows);
				$this->m_data['Id'] = $row['Id'];
				$this->m_data['ParentId'] = $row['ParentId'];
				$this->m_data['TopicOrder'] = $row['TopicOrder'];
				$this->m_names[$row['LanguageId']] = $row['Name'];
				foreach ($rows as $row) {
					$this->m_names[$row['LanguageId']] = $row['Name'];
				}
				$this->m_exists = true;
			} else {
				$this->m_exists = false;
			}
		}

		if ($this->m_exists) {
		    // Write the object to cache
		    $this->writeCache();
		}

        return $this->m_exists;
	} // fn fetch


	/**
	 * Create a new topic.
	 *
	 * @param array $p_values
	 * @return boolean
	 */
	public function create($p_values = null)
	{
		global $g_ado_db;
		$queryStr = "UPDATE AutoId SET TopicId = LAST_INSERT_ID(TopicId + 1)";
		$g_ado_db->Execute($queryStr);
		$this->m_data['Id'] = $g_ado_db->Insert_ID();
		$this->m_data['LanguageId'] = 1;
		if (isset($p_values['LanguageId'])) {
			$this->m_data['LanguageId'] = $p_values['LanguageId'];
		}
		$this->m_data['Name'] = "";
		if (isset($p_values['Name'])) {
			$this->m_names[$this->m_data['LanguageId']] = $p_values['Name'];
		}

		// compute topic order number
		$queryStr = "SELECT MIN(TopicOrder) AS min FROM Topics";
		$topicOrder = $g_ado_db->GetOne($queryStr) - 1;
		if ($topicOrder < 0) {
			$topicOrder = $this->m_data['Id'];
		}
		if ($topicOrder == 0) {
			$queryStr = "UPDATE Topics SET TopicOrder = TopicOrder + 1";
			$g_ado_db->Execute($queryStr);
			$topicOrder = 1;
		}
		$p_values['TopicOrder'] = $topicOrder;

		$success = parent::create($p_values);
		if ($success) {
			$this->m_exists = true;
			if (function_exists("camp_load_translation_strings")) {
				camp_load_translation_strings("api");
			}
			$logtext = getGS('Topic "$1" ($2) added', $this->m_data['Name'], $this->m_data['Id']);
			Log::Message($logtext, null, 141);
		}
		CampCache::singleton()->clear('user');
		return $success;
	} // fn create


	/**
	 * Delete the topic.
	 * @return boolean
	 */
	public function delete($p_languageId = null)
	{
		global $g_ado_db;
		$deleted = false;
		if (is_null($p_languageId)) {
			// Delete all translations
			$sql = "DELETE FROM Topics WHERE Id=".$this->m_data['Id'];
			$deleted = $g_ado_db->Execute($sql);
		} elseif (is_numeric($p_languageId)) {
			// Delete specific translation
			$sql = "DELETE FROM Topics WHERE Id=".$this->m_data['Id']." AND LanguageId=".$p_languageId;
			$deleted = $g_ado_db->Execute($sql);
		}

		// Change TopicOrder of another topics
		// if all translations of this topic removed
		if ($deleted) {
		    $sql = "SELECT COUNT(*) FROM Topics WHERE Id=".$this->m_data['Id'];
		    $qty = (int)$g_ado_db->GetOne($sql);
		    if (!$qty) {
    		    $sql = 'UPDATE Topics SET TopicOrder = TopicOrder - 1 WHERE TopicOrder > '.$this->m_data['TopicOrder'];
                $g_ado_db->Execute($sql);
		    }
        }

		// Delete the ATF metadata
        if ($deleted) {

            $sql = "SELECT * FROM TopicFields WHERE RootTopicId=". $this->m_data['Id'];

            $row = $g_ado_db->GetRow($sql);
            if ($row) {
                $delATF = new ArticleTypeField($row['ArticleType'], $row['FieldName']);
                $deleted = $delATF->delete();
            }
        }

		if ($deleted) {
			$this->m_exists = false;
			if (function_exists("camp_load_translation_strings")) {
				camp_load_translation_strings("api");
			}
			if (is_null($p_languageId)) {
				$name = implode(",", $this->m_names);
			} else {
				$name = $this->m_names[$p_languageId];
			}
			$logtext = getGS('Topic "$1" ($2) deleted', $name, $this->m_data['Id']);
			Log::Message($logtext, null, 142);
		}
		CampCache::singleton()->clear('user');
		return $deleted;
	} // fn delete


	/**
	 * @return string
	 */
	public function getName($p_languageId)
	{
		if (is_numeric($p_languageId) && isset($this->m_names[$p_languageId])) {
			return $this->m_names[$p_languageId];;
		} else {
			return "";
		}
	} // fn getName


	/**
	 * Set the topic name for the given language.  A new entry in
	 * the database will be created if the language does not exist.
	 *
	 * @param int $p_languageId
	 * @param string $p_value
	 *
	 * @return boolean
	 */
	public function setName($p_languageId, $p_value)
	{
		global $g_ado_db;
		if (!is_string($p_value) || !is_numeric($p_languageId)) {
			return false;
		}

		if (isset($this->m_names[$p_languageId])) {
			// Update the name.
			$oldValue = $this->m_names[$p_languageId];
			$sql = "UPDATE Topics SET Name='".mysql_real_escape_string($p_value)."' "
					." WHERE Id=".$this->m_data['Id']
					." AND LanguageId=".$p_languageId;
			$changed = $g_ado_db->Execute($sql);
		} else {
			// Insert the new translation.
			$oldValue = "";
			$sql = "INSERT INTO Topics SET Name='".mysql_real_escape_string($p_value)."' "
					.", Id=".$this->m_data['Id']
					.", LanguageId=$p_languageId"
					.", TopicOrder=".$this->m_data['TopicOrder']
					.", ParentId=".$this->m_data['ParentId'];
			$changed = $g_ado_db->Execute($sql);
		}
		if ($changed) {
			$this->m_names[$p_languageId] = $p_value;
			if (function_exists("camp_load_translation_strings")) {
				camp_load_translation_strings("api");
			}
			$logtext = getGS('Topic $1: ("$2" -> "$3") updated', $this->m_data['Id'], $oldValue, $this->m_names[$p_languageId]);
			Log::Message($logtext, null, 143);
		}
		return $changed;
	} // fn setName


	/**
	 * @return int
	 */
	public function getTopicId()
	{
		return $this->m_data['Id'];
	} // fn getTopicId


	/**
	 * Get all translations of the topic in an array indexed by
	 * the language ID.
	 *
	 * @return array
	 */
	public function getTranslations()
	{
	    return $this->m_names;
	} // fn getTranslations


	/**
	 * Return the number of translations of this topic.
	 *
	 * @return int
	 */
	public function getNumTranslations()
	{
		return count($this->m_names);
	} // fn getNumTranslations


	/**
	 * @return int
	 */
	public function getParentId()
	{
		return $this->m_data['ParentId'];
	} // fn getParentId

	/**
	 * @return int
	 */
	public function getTopicOrder()
	{
		return $this->m_data['TopicOrder'];
	} // fn getTopicOrder

	/**
	 * Return an array of Topics starting from the root down
	 * to and including the current topic.
	 *
	 * @return array
	 */
	public function getPath()
	{
		global $g_ado_db;
		$done = false;
		$currentId = $this->m_data['Id'];
		$stack = array();
		while (!$done) {
			$queryStr = 'SELECT * FROM Topics WHERE Id = '.$currentId;
			$rows = $g_ado_db->GetAll($queryStr);
			if (($rows !== false) && (count($rows) > 0)) {
				$row = array_pop($rows);
				$topic = new Topic();
				$topic->fetch($row);
				// Get all the translations
				foreach ($rows as $row) {
					$topic->m_names[$row['LanguageId']] = $row['Name'];
				}
				array_unshift($stack, $topic);
				$currentId = $topic->getParentId();
			} else {
				$done = true;
			}
		}
		return $stack;
	} // fn getPath


	/**
	 * Returns true if it was a root topic
	 * @return boolean
	 */
    public function isRoot()
    {
        return $this->m_data['ParentId'] == 0;
    } // fn isRoot


	/**
	 * Return true if this topic has subtopics.
	 *
	 * @return boolean
	 */
	public function hasSubtopics()
	{
		global $g_ado_db;
		// Returned the cached value if available.
		if (!is_null($this->m_hasSubtopics)) {
			return $this->m_hasSubtopics;
		}
		$queryStr = 'SELECT COUNT(*) FROM Topics WHERE ParentId = '.$this->m_data['Id'];
		$numRows = $g_ado_db->GetOne($queryStr);
		return ($numRows > 0);
	} // fn hasSubtopics


	/**
	 * Returns a topic object identified by the full name in the
	 * format topic_name:language_code
	 *
	 * @param string $p_fullName
	 * @return Topic object
	 */
	public static function GetByFullName($p_fullName)
	{
	    $components = preg_split('/:/', trim($p_fullName));
	    if (count($components) < 2) {
	        return null;
	    }
	    $name = $components[0];
	    $languageCode = $components[1];

	    $languages = Language::GetLanguages(null, $languageCode, null, array(), array(), false);
	    if (count($languages) < 1) {
	        return null;
	    }
        $languageObject = $languages[0];

        $topics = Topic::GetTopics(null, $languageObject->getLanguageId(), $name);
	    if (count($topics) < 1) {
	        return null;
	    }

	    return $topics[0];
	} // fn GetByFullName


	/**
	 * Search the Topics table.
	 *
	 * @param int $p_id
	 * @param int $p_languageId
	 * @param string $p_name
	 * @param int $p_parentId
	 * @param array $p_sqlOptions
	 * @return array
	 */
	public static function GetTopics($p_id = null, $p_languageId = null, $p_name = null,
					                 $p_parentId = null, $p_sqlOptions = null,
					                 $p_order = null, $p_countOnly = false)
	{
        global $g_ado_db;
		if (!$p_skipCache && CampCache::IsEnabled()) {
            $paramsArray['id'] = (is_null($p_id)) ? '' : $p_id;
            $paramsArray['language_id'] = (is_null($p_languageId)) ? '' : $p_languageId;
            $paramsArray['name'] = (is_null($p_name)) ? '' : $p_name;
            $paramsArray['parent_id'] = (is_null($p_parentId)) ? '' : $p_parentId;
            $paramsArray['sql_options'] = $p_sqlOptions;
            $paramsArray['order'] = $p_order;
            $paramsArray['count_only'] = (int)$p_countOnly;
            $cacheListObj = new CampCacheList($paramsArray, __METHOD__);
            $topics = $cacheListObj->fetchFromCache();
            if ($topics !== false && is_array($topics)) {
                return $p_countOnly ? $topics['count'] : $topics;
            }
        }

		$constraints = array();
		if (!is_null($p_id)) {
			$constraints[] = "`Id` = '$p_id'";
		}
		if (!is_null($p_languageId)) {
			$constraints[] = "`LanguageId` = '$p_languageId'";
		}
		if (!is_null($p_name)) {
			$constraints[] = "`Name` = '$p_name'";
		}
		if (!is_null($p_parentId)) {
			$constraints[] = "`ParentId` = '$p_parentId'";
		}
		if (is_array($p_order) && count($p_order) > 0) {
			$order = array();
			foreach ($p_order as $orderCond) {
				switch (strtolower($orderCond['field'])) {
					case 'default':
						$order['TopicOrder'] = $orderCond['dir'];
						break;
                	case 'byname':
                		$order['Name'] = $orderCond['dir'];
                		break;
                	case 'bynumber':
                		$order['Id'] = $orderCond['dir'];
                		break;
                }
			}
			if (count($order) > 0) {
				$p_sqlOptions['ORDER BY'] = $order;
			}
		}
		$tmpObj = new Topic();
        $queryStr = "SELECT DISTINCT Id FROM ".$tmpObj->m_dbTableName;
        if (count($constraints) > 0) {
        	$queryStr .= " WHERE ".implode(" AND ", $constraints);
        }
        $queryStr = DatabaseObject::ProcessOptions($queryStr, $p_sqlOptions);
        if ($p_countOnly) {
        	$queryStr = "SELECT COUNT(*) FROM ($queryStr) AS topics";
        	$topics['count'] = $g_ado_db->GetOne($queryStr);
        } else {
        	$topics = array();
        	$rows = $g_ado_db->GetAll($queryStr);
        	foreach ($rows as $row) {
        		$topics[] = new Topic($row['Id']);
        	}
        }

        if (!$p_skipCache && CampCache::IsEnabled()) {
            $cacheListObj->storeInCache($topics);
        }

        return $topics;
	} // fn GetTopics


	/**
	 * Returns the subtopics from the next level (not all levels below) in an array
	 * of topic identifiers.
	 * @param array $p_returnIds
	 */
	public function getSubtopics($p_returnIds = false)
	{
        global $g_ado_db;

		$sql = "SELECT DISTINCT Id FROM Topics WHERE ParentId = " . (int)$this->m_data['Id'];
		$rows = $g_ado_db->GetAll($sql);
		$topics = array();
		foreach ($rows as $row) {
			$topics[] = $p_returnIds ? $row['Id'] : new Topic($row['Id']);
		}
		return $topics;
	} // getSubtopics


	/**
	 * Traverse the tree from the given topic ID.
	 *
	 * @param array $p_tree
	 * @param array $p_path
	 * @param int $p_topicId
	 */
	private static function __TraverseTree(&$p_tree, $p_path, $p_topicId = 0)
	{
		global $g_ado_db;
		$sql = "SELECT * FROM Topics WHERE ParentId = ".$p_topicId
				." ORDER BY TopicOrder ASC, LanguageId ASC";
		$rows = $g_ado_db->GetAll($sql);
		if ($rows) {
			$previousTopic = new Topic();

			$currentTopics = array();

			// Get all the topics at the current level of the tree.
			// Translations of a topic are merged into a single topic.
			foreach ($rows as $row) {
				// If its a translation of the previous topic, add it as a translation.
				if ($previousTopic->m_data['Id'] == $row['Id']){
					$previousTopic->m_names[$row['LanguageId']] = $row['Name'];
				} else {
					// This is a new topic, not a translation.
					$currentTopics[$row['Id']] = new Topic();
					$currentTopics[$row['Id']]->fetch($row);

					// Remember this topic so we know if the next topic
					// is a translation of this one.
					$previousTopic =& $currentTopics[$row['Id']];

					// Create the entry in the tree for the current topic.

					// Copy the current path.  We need to make a copy
					// because if we added to $p_path, it would get longer
					// each time around the loop.
					$newPath = $p_path;

					// Add the current topic to the path.
					$newPath[$row['Id']] =& $currentTopics[$row['Id']];

					// Add the path to the tree.
					$p_tree[] = $newPath;

					// Descend the tree - dont worry, the translations will be added
					// the next time around the loop.
					Topic::__TraverseTree($p_tree, $newPath, $row['Id']);
				}
			} // foreach

		}
	} // fn __TraverseTree


	/**
	 * Change the topic's position in the order sequence
	 * relative to its current position.
	 *
	 * @param string $p_direction -
	 * 		Can be "up" or "down".  "Up" means towards the beginning of the list,
	 * 		and "down" means towards the end of the list.
	 *
	 * @param int $p_spacesToMove -
	 *		The number of spaces to move the article.
	 *
	 * @return boolean
	 */
	public function positionRelative($p_direction, $p_spacesToMove = 1)
	{
		global $g_ado_db;

		CampCache::singleton()->clear('user');
		$this->fetch();
		// Get the article that is in the final position where this
		// article will be moved to.
		$compareOperator = ($p_direction == 'up') ? '<' : '>';
		$order = ($p_direction == 'up') ? 'desc' : 'asc';
		$queryStr = 'SELECT DISTINCT(Id), TopicOrder FROM Topics '
					.' WHERE TopicOrder '.$compareOperator.' '.$this->m_data['TopicOrder']
					.' AND ParentId='.$this->m_data['ParentId']
					.' ORDER BY TopicOrder ' . $order
		     		.' LIMIT '.($p_spacesToMove - 1).', 1';
		$destRow = $g_ado_db->GetRow($queryStr);
		if (!$destRow) {
			return false;
		}
		// Change position of the destination.
		if ($p_direction == 'up') {
		    $operator = '+';
		    $compareOperator = '>=';
		    $compareOperator2 = '<';
		} else {
		    $operator = '-';
		    $compareOperator = '<=';
		    $compareOperator2 = '>';
		}
		$queryStr2 = 'UPDATE Topics SET TopicOrder = TopicOrder '.$operator.' 1 '
					.' WHERE TopicOrder '.$compareOperator.' '.$destRow['TopicOrder']
					.' AND TopicOrder '.$compareOperator2.' '.$this->m_data['TopicOrder'];
		$g_ado_db->Execute($queryStr2);

		// Change position of this topic to the destination position.
		$queryStr3 = 'UPDATE Topics SET TopicOrder = ' . $destRow['TopicOrder']
					.' WHERE Id = ' . $this->m_data['Id'];
		$g_ado_db->Execute($queryStr3);
		CampCache::singleton()->clear('user');

		// Re-fetch this article to get the updated article order.
		$this->fetch();
		return true;
	} // fn positionRelative


	/**
	 * Move the topic to the given position (i.e. reorder the topic).
	 * @param int $p_moveToPosition
	 * @return boolean
	 */
	public function positionAbsolute($p_moveToPosition = 1)
	{
		global $g_ado_db;

		CampCache::singleton()->clear('user');
		$this->fetch();
		// Get the topic that is in the location we are moving
		// this one to.
		$queryStr = 'SELECT Id, LanguageId, TopicOrder FROM Topics '
					.' WHERE ParentId='.$this->m_data['ParentId']
					.' AND TopicOrder='.$p_moveToPosition
					.' ORDER BY TopicOrder ASC LIMIT 1';
		$destRow = $g_ado_db->GetRow($queryStr);
		if (!$destRow) {
			return false;
		}

		// Reposition destination topic.
		$operator = $destRow['TopicOrder'] < $this->m_data['TopicOrder'] ? '+' : '-';
		if ($destRow['TopicOrder'] > $this->m_data['TopicOrder']) {
		    $compareOperator =  '>';
		    $compareOperator2 =  '<=';
		} else {
		    $compareOperator =  '<';
		    $compareOperator2 =  '>=';
		}
		$queryStr = 'UPDATE Topics '
					.' SET TopicOrder = TopicOrder '.$operator.' 1 '
					.' WHERE TopicOrder '.$compareOperator.' '.$this->m_data['TopicOrder']
					.' AND TopicOrder '.$compareOperator2.' '.$destRow['TopicOrder'];
		$g_ado_db->Execute($queryStr);

		// Reposition this topic.
		$queryStr = 'UPDATE Topics '
					.' SET TopicOrder='.$destRow['TopicOrder']
					.' WHERE Id='.$this->m_data['Id'];
		$g_ado_db->Execute($queryStr);
		CampCache::singleton()->clear('user');

		$this->fetch();
		return true;
	} // fn positionAbsolute


	/**
	 * Get all the topics in an array, where each element contains the entire
	 * path for each topic.  Each topic will be indexed by its ID.
	 * For example, if we have the following topic structure (IDs are
	 * in brackets):
	 *
	 * sports (1)
	 *  - baseball (2)
	 *  - soccer (3)
	 *    - player stats (4)
	 *    - matches (5)
	 * politics (6)
	 *  - world (7)
	 *  - local (8)
	 *
	 *  ...then the returned array would look like:
	 *  array(array(1 => "sports"),
	 *        array(1 => "sports", 2 => "baseball"),
	 *        array(1 => "sports", 3 => "soccer"),
	 *        array(1 => "sports", 3 => "soccer", 4 => "player stats"),
	 *        array(1 => "sports", 3 => "soccer", 5 => "matches"),
	 *        array(6 => "politics"),
	 *        array(6 => "politics", 7 => "world"),
	 *        array(6 => "politics", 8 => "local")
	 *  );
	 *
	 * @param int $p_startingTopicId
	 * @return array
	 */
	public static function GetTree($p_startingTopicId = 0)
	{
		$tree = array();
		$path = array();
		Topic::__TraverseTree($tree, $path, $p_startingTopicId);
		return $tree;
	} // fn GetTree

    /**
     * Update order for all items in tree.
     *
     * @param array $order
     *      $parent =>  array(
     *          $order => $topicId
     *      );
     *  @return bool
     */
    public static function UpdateOrder(array $p_order)
    {
		global $g_ado_db;

        $g_ado_db->StartTrans();
        foreach ($p_order as $parentId => $order) {
            foreach ($order as $topicOrder => $topicId) {
                list(, $topicId) = explode('_', $topicId);
                $queryStr = 'UPDATE Topics
                    SET TopicOrder = ' . ((int) $topicOrder) . '
                    WHERE Id = ' . ((int) $topicId);
                $g_ado_db->Execute($queryStr);
            }
        }
        $g_ado_db->CompleteTrans();

        return TRUE;
    } // fn UpdateOrder

} // class Topics

?>
