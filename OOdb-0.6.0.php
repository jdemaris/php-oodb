<?
/**
 * An Object Oriented Database Interface for PHP (PhpOOdb).
 *
 * This is a package to make coding complex, database driven applications in PHP a whole lot
 * easier. Please note that this package requires PHP 5.1.0 or greater to operate. To be honest,
 * you probably won't be using classes and objects much in PHP pre-5.0 anyway since so many
 * features were lacking. If you really desperately need to have a PHP 4.x version of this, then
 * please contact me at <b>justin dot demaris at gmail dot com</b> and I will see what I can do
 * to port this down for you.
 *
 * Vocabulary:
 *
 * Local vs Remote - A lot of the documentation is written from the perspective of a class that
 * is extending the dbObject and using the different features. For example, if we are implementing
 * the Calendar class, then the Calendar class is considered the "Local" class. When we have
 * complex relationships, the Calendar class may be related to other classes, for example, a Category.
 * In that case, from the perspective of the class we are implementing, the Category class is the
 * "remote" class.
 *
 * CHANGES SINCE 0.5.1-beta:
 * ---------------------------
 * - Addition of the dbFactory class that can be used to create instances of classes that extend the
 * dbObject.
 * - Addition of the wildcard load ability to the dbFactory class to load a set of objects based on 
 * a wild card.
 * - Updates only update the one column that was modified to reduce query size / overhead
 *
 * @author Justin DeMaris <justin.demaris@gmail.com>
 * @package DatabaseAbstract
 * @version 0.6.0
 *
 * @todo Add a flag to allow bulk changes to objects so that changing properties doesn't immediately update the db
 * @todo Look into allowing single objects to be bound to multiple tables...
 */

/**
 * Generic template for handling connections to different types of databases.
 * The system currently comes with a MySql adapter by default.
 *
 * Extensions of this class provide the functionality to actually execute queries. Any query should
 * be run by a call to runQuery. This class also provides some extra performance improvement because it 
 * does not make the connection to the database until a query is run. This allows us to fully utilize
 * the Smarty template engine's cache feature because we can include the database connection info
 * in a global file, and set up a global Database object, and then check if the page we are going
 * to serve is cached. If it isn't cached, then all database queries are run as usual and the
 * system works as expected. If the page IS cached, then no queries will actually be run, in which
 * case, PHP will never talk to the database, saving overhead in time as well as SQL connections.
 *
 * So long as all of these functions are implemented, the system works completely properly with any
 * sort of back end, be it MySql, PostgreSQL, flat files, or a dead badger (this may require further
 * hardware drivers, see http://www.strangehorizons.com/2004/20040405/badger.shtml for help).
 *
 * @package DatabaseAbstract
 */
abstract class dbAdapter {
	/**
	  * Holds the connection info - used during optimization to hold the connection information
	  * until a query is actually going to be run. Saves us from having to make a database connection
	  * with every page load even if we aren't going to use it.
	  */
	protected $_connection_info = array();

	/**
	 * Indicator flag. Marks if we are connected or not. If we aren't, then we know
	 * we have to create a connection before running a query.
	 */
	protected $_is_connected = false;
	
	/**
	 * Default Constructor.
	 *
	 * This generally does not have to be over-ridden as no connections are actually
	 * made right here. The actual work of the connection is done by the _connect function.
	 * This just stores the provided information for future reference when we DO connect.
	 *
	 * @param array $connection_info The information used to connect to the database. Will vary by adapter.
	 */
	public function __construct($connection_info) {
		// Make sure we go the proper format of input
		if ( !is_array($connection_info) )
			throw new Exception("Improperly Formatted Connection Information!");
			
		// Store connection information
		$this->_connection_info = $connection_info;
	}
	
	/**
	 * Runs an actual query passed to it and returns the results as an array.
	 *
	 * @param string $query The query to pass on to the database
	 */
	public abstract function runQuery($query);
	
	/**
	 * If the connection to the database has not been made yet, then open the connection.
	 */
	protected abstract function _connect();
	
	/**
	 * Get a row from the given table.
	 *
	 * @param string $table Name of the table to get the row from
	 * @param string $key Name of the field to use when selecting which row.
	 * @param mixed $key_value Value that $key should be.
	 * @return array A single associative array with each field being a key in the array. False on no such row.
	 */
	public abstract function getRow($table, $key, $key_value);
	
	/**
	 * Write a row into the given table (Update Row)
	 *
	 * @param string $table Name of the table to update data in
	 * @param string $key Name of the field to use identify which field to update.
	 * @param array $row Associative array of data to update with. $row[$key] used as value to $key
	 * @return void
	 */
	public abstract function putRow($table, $key, $row);
	
	/**
	 * Write a row into the given table (New Row)
	 *
	 * @param string $table Name of the table to add the row into
	 * @param array $row Associative array of fields => values to put into that row
	 * @return mixed The value of unique identifier for this new row if it exists.
	 */
	public abstract function newRow($table, $row);
	
	/**
	 * Remove a row from the given table
	 *
	 * @param string $table Name of the table to delete the row from
	 * @param string $key Name of the field to use when selecting which row.
	 * @param mixed $key_value Value that $key should be.
	 * @return void
	 */
	public abstract function delRow($table, $key, $key_value);
	
	/**
	 * Protect Variables to Prevent Injection. Whatever method is best for properly escaping values
	 * should be called by this function.
	 *
	 * @param string $value Value to be escaped
	 * @return string Escaped version of the string.
	 */
	public abstract function escape($value);
	
	/**
	 * Get multiple rows based on a set of paramters.
	 *
	 * For example, if we want to get all rows with name equal to 'bob' and shoe size equal
	 * to 12, we would pass in array("name"=>"bob", "shoe_size" => 12).
	 *
	 * @param string $table The table to get the rows from
	 * @param array $params The restrictions on the query (see function documentation)
	 * @return array Returns an array of rows. Each row represented by associative array.
	 */
	public abstract function getRows($table, $params = array());
	
	/**
	 * Delete multiple rows based on a set of restrictions.
	 *
	 * @param string $table The name of the table to delete the rows from
	 * @param array $params Set of restrictions on the query. (See getRows documentation)
	 * @return integer Number of rows deleted.
	 */
	public abstract function delRows($table, $params);
	
	/**
	 * Update multiple rows based on a set of restrictions.
	 *
	 * The values paramter is of the same format as the $params parameter, described in
	 * the getRows documentation. This method takes all of the rows specifed by the second
	 * argument and updates the fields specified as the keys of the associative array in 
	 * the second argument with the values associated with them.
	 *
	 * @param string $table Name of the table to update
	 * @param array $params Restrictions on which rows to update.
	 * @param array $values Associative array of which fields to update with which values
	 * @return integer Number of rows altered by the change.
	 */
	public abstract function putRows($table, $params, $values);
}


/**
 * The Database class is a level of abstraction and buffering between the DBMS and the application.
 * 
 * This class also provides the interface to the objects that PHP version of the database by providing
 * the methods to retrive, update, and remove these object's serialized versions from the database.
 *
 * @package DatabaseAbstract
 */
class dbDatabase {
	/**
	 * Database Adapter Object
	 */
	private $_db = NULL;
	
	/**
	 * A place where we store references to objects created so that when another instance of
	 * that object is requested, we can just return the reference. This serves two purposes.
	 * First, it reduces database queries, which is needed since this system is very database
	 * intensive. Secondly, it makes it so that all Calendar objects with calendar_id = 1, for
	 * example, are all references to the same object. Any changes made via one reference will
	 * be reflected throughout the system.
	 */
	private $_object_cache = array();
	
	/**
	 * Create this new database object with the given connection type
	 *
	 * @param string $adapter Name of the adapter to use. Currently 'MySQL' only supported.
	 * @param array $connection_info Info to use when connecting to the database.
	 * @param string $root_dir The root directory that the OOdb.php is located at. Used for finding adapters.
	 */
	public function __construct($adapter, $connection_info, $root_dir = '') {
		// Load the database adapter
		require_once $root_dir."dbAdapter/{$adapter}.php";
		$adapter_class = 'dbAdapter_'.$adapter;
		
		// Create Database Connection
		$this->_db = new $adapter_class($connection_info);
	}
	
	/**
	 * Retrieves an entry from the database and creates the appropriate object to bind to it.
	 *
	 * @param string $class_name Name of the class to grab an object of from the database.
	 * @param integer $key_value Value of the unique identifier for which object to grab.
	 * @return dbObject A reference to the object that matches the given value. NULL if doesn't exist.
	 */
	public function getObject($class_name, $key_value) {
		// If it's not in the cache, load it
		if ( !isset($this->_object_cache[$class_name][$key_value]) ) {
			$class_vars = call_user_func(array($class_name, '_dbInfo'));
			$row = $this->_db->getRow($class_vars['table'], $class_vars['key'], $key_value);
			if ( $row === false ) return NULL;
			$this->_object_cache[$class_name][$key_value] = new $class_name($this, $row);
		}
		
		return $this->_object_cache[$class_name][$key_value];
	}

	/**
	 * Create a new object of the given type with a matching db entry
	 *
	 * @param string $class_name Name of the class to create an instance of (must extend dbObject)
	 * @param array $values Default values to assign to the object
	 * @return dbObject Reference to the created object.
	 */
	public function newObject($class_name, $values) {
		// Get the unique identifying information
		$class_info = call_user_func(array($class_name, '_dbInfo'));
		
		// If no values were given, then create a blank row
		if ( !is_array($values) || count($values) == 0 )
			$values = $class_info['key'];

		// Create a new blank row
		$id = $this->_db->newRow($class_info['table'], $values);
		
		// Create a new object to match to this entry
		return $this->getObject($class_name, $id);
	}
	
	/**
	 * Saves information about a provided object to the database.
	 *
	 * @param dbObject $object Reference to the object we want to update the database about.
	 * @param string $field OPTIONAL. Name of the field if we are updating a single field only.
	 * @return void
	 */
	public function putObject($object, $field = NULL) {
		// Get the unique identifying information
		$table = $object->getTable();
		$key   = $object->getKey();

		// Create a row of information
		$data = $object->_getValues();
		
		// If we are only updating one field, then rearrange the data
		if ( $field != NULL ) 
			$data = array($key => $data[$key], $field => $data[$field]);

		// Push this to the database
		$this->_db->putRow($table, $key, $data);
	}
	
	/**
	 * The factory itself. Loads the object from the database or creates the object if it doesn't
	 * exist.
	 *
	 * @param string $obj_type Name of the class to generate the object for
	 * @param integer $id_value OPTIONAL. Identifying value for the instance. If blank, creates new.
	 */
	public function load($obj_type, $id_value = -1) {
		// If it's a default, then create the new entry
		if ( $id_value == -1 ) {
			return $this->newObject($obj_type, $id_value);
		}
		
		// Otherwise, load it from the database
		else
			return $this->getObject($obj_type, $id_value);
	}
	
	/**
	 * Deletes an object and its corresponding database rows, along with all of the
	 * different entries that reference or relate to it.
	 *
	 * @param dbObject $object Reference to the object we are deleting
	 */
	public function delObject($object) {
		// Get the unique identifying information
		$class_name = get_class($object);
		$table = $object->getTable();
		$key   = $object->getKey();
		$key_value = $object->getKeyValue();
		
		// Tell the object to clean up and remove it's relationships
		$object->_delRelations();
		
		// Remove the entry from the database for the object
		$this->_db->delRow($table, $key, $key_value);
		
		// Remove the object from the local cache
		unset($this->_object_cache[$class_name][$key_value]);
		
		// Return a NULL reference
		return NULL;
	}

	/**
	 * Get all of the objects of a particular type from the database.
	 *
	 * @param string $class_name Name of the class we are creating objects of
	 * @return array Array of dbObject's
	 */
	public function getAllObjects($class_name) {
		// Get information about the class
		$dbInfo = call_user_func(array($class_name, _dbInfo));
		$table = $dbInfo['table'];
		$key   = $dbInfo['key'];

		// Get all rows from the given table
		$rows = $this->_db->getRows($table);

		$objects = array();

		// Convert each row to an object
		foreach ( $rows as $row ) {
			// Create the object
			$obj = $this->getObject($class_name, $row[$key]);
			
			// Add it to the array
			$objects[] = $obj;
		}

		// Return the list of objects
		return $objects;
	}

	/**
	 * Create a non-indexing row in a relationship table. Used for MANY-TO-MANY relationships.
	 *
	 * @param string $table_name
	 * @param dbObject $local_obj
	 * @param dbObject $remote_obj
	 * @param array $properties OPTIONAL
	 */
	public function addListRow($table_name, $local_obj, $local_key, $remote_obj, $remote_key, $properties = array()) {
		// validate input
		if ( !$local_obj instanceof dbObject || !$remote_obj instanceof dbObject )
			throw new Exception("Local and remote objects must be dbObjects");
	
		// Combine it all into one key/value array
		$properties[$local_key] = $local_obj->$local_key;
		$properties[$remote_key] = $remote_obj->$remote_key;
	
		// Create the row
		$this->_db->newRow($table_name, $properties);
		
		// Return success
		return true;
	}
	
	/**
	 * Retrieve a list of remote rows to generate relationship objects with.
	 *
	 * @param string $assoc_table The table used in the association
	 * @param dbObject $local_obj The object that we want to use as the base for the search
	 * @param string $local_key The field in the local_obj to use as the key for the search
	 * @return array An array of rows.
	 */
	public function getListRows($assoc_table, $local_obj, $local_key) {
		$params = array($local_key => $local_obj->$local_key);
		return $this->_db->getRows($assoc_table, $params);
	}
	
	/**
	 * Remove the list row entry from the relationship table
	 *
	 * @param string $table_name Name of the table to remove the row from
	 * @param dbObject $local_obj Reference to the object used as the base reference
	 * @param string $local_key The field of the local_obj that is used as the key in the selection
	 * @param dbObject $remote_obj Reference to the object we are removing the association with
	 * @param string $remote_key The field of the remote_obj that is used as the key in the selection
	 * @return integer Number of rows deleted.
	 */
	public function delListRow($table_name, $local_obj, $local_key, $remote_obj, $remote_key) {
		// validate input
		if ( !$local_obj instanceof dbObject || !$remote_obj instanceof dbObject )
			throw new Exception("Local and remote objects must be dbObjects");

		// Set up local restrictions
		$key = $local_key;
		$key_value = $local_obj->$local_key;
		$params[$key] = $key_value;
		
		// Set up remote restrictions
		$key = $remote_key;
		$key_value = $remote_obj->$remote_key;
		$params[$key] = $key_value;
		
		// Remove the row
		$num_deleted = $this->_db->delRows($table_name, $params);
		
		// Return success
		return $num_deleted;
	}
	
	/**
	 * Update the list row entry in the relationship table with new properties
	 *
	 * @param string $table_name Name of the table to update the row in
	 * @param dbObject $local_obj Reference to the object used as the base reference
	 * @param string $local_key The field of the local_obj that is used as the key in the selection
	 * @param dbObject $remote_obj Reference to the object we are altering the association with
	 * @param string $remote_key The field of the remote_obj that is used as the key in the selection
	 * @param array $properties Associative array containing the properties of the relationship to update
	 * @return integer Number of rows affected by the change.
	 */
	public function putListRow($table_name, $local_obj, $local_key, $remote_obj, $remote_key, $properties) {
		// validate input
		if ( !$local_obj instanceof dbObject || !$remote_obj instanceof dbObject )
			throw new Exception("Local and remote objects must be dbObjects");

		// Set up local restrictions
		$key = $local_key;
		$key_value = $local_obj->$local_key;
		$params[$key] = $key_value;
		
		// Set up remote restrictions
		$key = $remote_key;
		$key_value = $remote_obj->$remote_key;
		$params[$key] = $key_value;
		
		// Update the row
		$num_editted = $this->_db->putRows($table_name, $params, $properties);
		
		// Return success
		return $num_editted;
	}
	
	/**
	 * Allows direct access to the database connection. Passes queries directly to the adapter.
	 *
	 * @param string $query The query to run on the database system.
	 * @return array An array of associative arrays representing rows as a result of the query.
	 */
	public function runQuery($query) {
		return $this->_db->runQuery($query);
	}
	
	/* NOT YET IMPLEMENTED
	 * Returns an array of all of the objects of the given type that match the given parameter
	 * specifications. The second paramter is an associative array of the field=>pattern layout
	 * where the pattern is anything that would fit into a mysql LIKE clause.
	 *
	 * @param string $obj_type Type of the object to find rows for
	 * @param array $params Associative array of fields => patterns to use as the query
	 * @return array dbObject Array of instances of $obj_type classes that match the parameters
	public function search($obj_type, $params) {
		return array();
	}
	 */
}


/**
 * A wrapper to contain properties and information about ONE-TO-MANY or MANY-TO-MANY relationships
 * between objects.
 *
 * There are two ways that this class is used. First, if the list is being used to represent a
 * MANY-TO-MANY relationship, then an associative table must be used in the database. In that case,
 * any changes to the relationships will update this associative table. For example, if we have a
 * database schema like this:
 * <pre>
 *        Calendars               CategoryAssign             Categories
 *       ---------------         -----------------          -----------------
 *       | calendar_id |  ---->  | calendar_id   |          | category_id   |
 *       | cal_name    |         | category_id   |   <----- | category_name |
 *       ---------------         | is_shown      |          -----------------
 *                               -----------------
 * </pre>
 * and we want to make it so that Calendar A is now a member of Category B, then we would create
 * an entry inside of the CategoryAssign table with the calendar_id of Calendar A and the category_id
 * of Category B. With the dbList, we can do this using methods. For example, if we have a Calendar
 * object named $CalA, and a Category object named $CatB, then we could call:
 * <code>$CalA->categories->add($CatB);</code>
 *
 * This would update the associations in the local objects themselves as well as in the database. Note
 * that when creating these associations, the properties of the relationship are set to their default
 * values. In this case, is_shown would be a tiny_int (either 1 or 0) in the database and would get
 * set to whatever the default is, probably 0. If we want to set is_shown to 1 when we create the
 * association, then we would call:
 * <code>$CalA->categories->add($CatB, array("is_shown"=>1));</code>
 * We can set the values of any of the properties of the association this way. You would use the same
 * method for changing the properties of an association with the update method.
 *
 * It is important to note that because of these properties, you cannot access one of the category objects
 * directly. When you call:
 * <code>$CalA->categories->getAll()</code>
 * you get an array of all of the associated entries. Each entry in the array is an associative array
 * with the key being the name of the property it contains. The special case is that the 'object' key
 * is reserved to contain the reference to the object that is being related to. For this reason, there
 * can be no properties of the relationship named 'object'.
 *
 * The other way this list is used is with a ONE-TO-MANY association, which occurs when we have a
 * database schema like this:
 * <pre>
 *       Events                  Occurrences
 *      ----------------        --------------
 *      | event_id     |<-------| o_event_id |
 *      | event_detail |        | a_date     |
 *      ----------------        --------------
 * </pre>
 * where every occurrence belongs to an event and an event can have multiple occurrences, the Event object
 * will have a ONE-TO-MANY relationship with the Occurrence. This case is significantly simpler because the
 * link does not depend on a separate table and the relationship itself does not have any properties. You
 * would still treat this the same way as you would if it was MANY-TO-MANY from the usability perspective.
 * For example, to get a list of all of the Occurences assigned to an Event named $EventA, you would call:
 * <pre>$EventA->occurrences->getAll()</pre>
 *
 * In order to maintain concurrency, any changes made through the dbList are reflected in the objects. For
 * example, if you call:
 * <code>$EventA->occurences->add($OccurenceB);</code>
 * this will assign the occurrence to EventA. The Occurence object will be updated so that o_event_id is
 * assigned to the event_id of EventA and the database is updated to reflect this as well.
 */
class dbList {
	protected $_db = NULL;
	protected $_assoc_table = '';
	protected $_local_key = '';
	protected $_remote_key = '';
	
	protected $_is_set = false;

	protected $_owner = NULL;
	protected $_remote_class = '';
	protected $_values = array();
	
	/**
	 * Constructor for a dbList. Never called directly by a user.
	 *
	 * @param dbDatabase $db Reference to the database object that all queries will run through
	 * @param string $assoc_table Name of the table that is used to hold relationship. Many-to-many only. Empty string otherwise.
	 * @param string $local_key The name of the key in the owner used to make the link
	 * @param dbObject $owner Reference to the object that contains this relationship.
	 * @param string $remote_key Name of the field in the remote class used to make the link
	 * @param string $remote_class Name of the class (type of object) that we are creating a link to. Must extend dbObject.
	 */
	public function __construct ( $db, $assoc_table, $local_key, $owner, $remote_key, $remote_class ) {
		// Store relationship information
		$this->_db = $db;
		$this->_owner = $owner;
		$this->_assoc_table = $assoc_table;
		$this->_local_key = $local_key;
		$this->_remote_key = $remote_key;
		$this->_remote_class = $remote_class;
	}

	/**
	 * Retrieve a related object from this relationship by it's unique identifier.
	 *
	 * @param integer $id The unique identifier of the related object we want
	 * @return dbObject The object specified, or NULL if it doesn't exist.
	 */
	public function get($id) {
		// Load from database if not yet loaded
		if ( !$this->_is_set )
			$this->_getObjects();

		// If it's a one-to-many, make sure the object still points here
		else if ( $this->_assoc_table == '' ) {
			$remote_key = $this->_remote_key;
			$local_key  = $this->_local_key;
			
			// If it no longer points here, then re-generate the cache
			if ( $this->_values[$id]->$remote_key != $this->_owner->$local_key )
				$this->_getObjects();
		}

		// Return reference to the object
		if ( isset($this->_values[$id]) )
			return $this->_values[$id];
		else
			return NULL;
	}
	
	/**
	 * Populates the list of related objects in the local cache.
	 */
	private function _getObjects() {
		// Reset local array
		$this->_values = array();
		
		// Where we get the list from depends on whether this is many-to-many or one-to-many
		if ( $this->_assoc_table != '' ) {	// MANY-TO-MANY
			// If we are pulling from an associative table, then pull now
			$rows = $this->_db->getListRows($this->_assoc_table, $this->_owner, $this->_local_key);
			$use_key = $this->_remote_key;
		} else {							// ONE-TO-MANY
			// Otherwise we don't have to worry about properties of the relationship
			$dbInfo = call_user_func(array($this->_remote_class, _dbInfo));
			$remote_table = $dbInfo['table'];
			$rows = $this->_db->getListRows($remote_table, $this->_owner, $this->_local_key);
			$use_key = $dbInfo['key'];
		}

		// Convert the list of entries into objects
		foreach ( $rows as $row ) {
			// Get the unique id used to create object
			$key_value = $row[$use_key];

			// Store the properties of the relationship
			$this->_values[$key_value] = $row;
			unset ( $this->_values[$key_value][$this->_remote_key] );
			
			// Create an object entry
			$this->_values[$key_value]['object'] = $this->_db->getObject($this->_remote_class, $key_value);
		}

		// Update flag
		$this->_is_set = true;
	}
	
	/**
	 * Retrieve the whole list of related objects and properties from the local storage.
	 *
	 * @return array An array of all objects and their properties.
	 */
	public function getAll() {
		// If we haven't loaded these from the database yet, do it now
		if ( !$this->_is_set )
			$this->_getObjects();
	
		// Return the values
		return $this->_values;
	}

	/**
	 * Add a new entry
	 *
	 * @param dbObject $remote_obj
	 * @param array $properties Information about the relationship
	 * @return boolean False if it already exists, true if succesfully added.
	 */
	public function add($remote_obj, $properties = array()) {
		// Validate the input
		if ( isset($properties['object']) )
			throw new Exception("Cannot have relationship property named object!");
		$key = $this->_remote_key;
		if ( is_null($remote_obj->$key) )
			throw new Exception("Error - remote object doesn't have key $key");
	
		// Make sure that we have gotten the values
		if ( !$this->_is_set )
			$this->_getObjects();
		
		// Check if entry is already in the local cache
		if ( isset($this->_values[$remote_obj->$key]) )
			return false;
		
		// Add the entry to the local cache
		$id = $remote_obj->$key;
		$this->_values[$id]['object'] = $remote_obj;
		
		// Add the properties as well
		foreach ( $properties as $key => $value )
			$this->_values[$id][$key] = $value;
		
		// Update the object for one-to-many
		if ( $this->_assoc_table == '' ) {
			$local_key = $this->_local_key;
			$remote_key = $this->_remote_key;
			$local_key_val = $this->_owner->$local_key;
			$remote_obj->$remote_key = $local_key_val;
		}
		
		// Update the database for many-to-many
		else {
			$this->_db->addListRow($this->_assoc_table,

								   $this->_owner,
								   $this->_local_key,
								   $remote_obj,
								   $this->_remote_key,
								   $properties);
		}
		
		return true;
	}
	
	/**
	 * Remove the given object from the local list of objects
	 *
	 * @param dbObject $remote_obj
	 * @return boolean false if no matching entry. true on success.
	 */
	public function remove($remote_obj) {
		// Validate input
		if ( !$remote_obj instanceof dbObject )
			return false;
		
		// Make sure that we have gotten the values
		if ( !$this->_is_set )
			$this->_getObjects();
		
		// Check if we actually have this object in the list
		$remote_key = $this->_remote_key;
		if ( !isset($this->_values[$remote_obj->$remote_key]) )
			return false;
		
		// Update the object itself for one-to-many relationships
		if ( $this->_assoc_table == '' ) {
			$remote_key = $this->_remote_key;
			$local_key  = $this->_local_key;
			
			// Remove the pointer to here
			$remote_obj->$remote_key = 0;
		}
		
		// Or remove the linking row for many-to-many relationships
		else {
			// And update the database
			$this->_db->delListRow($this->_assoc_table,
								   $this->_owner,
								   $this->_local_key,
								   $remote_obj,
								   $this->_remote_key);
		}
	
		// And remove the local cache entry
		unset($this->_values[$remote_obj->$remote_key]);
	}
	
	/**
	 * Set the properties of the relationship to the given object to be the
	 * new given values.
	 *
	 * @param dbObject $remote_obj The object whose relationship we want to change the properties of.
	 * @param array $properties Associative array of the fields and values we want to change.
	 * @return boolean False if object not in relationship. True on successful add.
	 */
	public function update($remote_obj, $properties) {
		// Validate input
		if ( !$remote_obj instanceof dbObject )
			throw new Exception("Given remote object not an instance of dbObject!");
		if ( isset($properties['object']) )
			throw new Exception("Cannot have relationship property named object!");
	
		// Make sure that we have gotten the values
		if ( !$this->_is_set )
			$this->_getObjects();
		// If it's a one-to-many, make sure the object still points here
		else if ( $this->_assoc_table == '' ) {
			$remote_key = $this->_remote_key;
			$local_key  = $this->_local_key;
			
			// If it no longer points here, then re-generate the cache
			if ( $this->_values[$id]->$remote_key != $this->_owner->$local_key )
				$this->_getObjects();
		}

		// Make sure we have this object
		$id = $remote_obj->getKeyValue();
		if ( !isset($this->_values[$id]['object']) )
			return false;

		// Update the object
		foreach ( $properties as $key => $value )
			$this->_values[$id][$key] = $value;
		
		// Update the database
		$this->_db->putListRow($this->_assoc_table,
		                       $this->_owner,
							   $this->_local_key,
							   $remote_obj,
							   $this->_remote_key,
							   $properties);
		return true;
	}
}

/**
 * The dbObject class is a class that allows us to bind PHP objects to a database.
 * 
 * This class is an abstract class that must be extended to be useful. It provides
 * all of the expanded functionality required for changes to the object itself to
 * be reflected immediately in the database.
 *
 * This class design supports pretty much any type of relational database schema, or
 * at least any schema that I have ever used. If you find one that it does not support,
 * then please let me know and I will do my humble best to fix that. The documentations
 * are written assuming that the unique identifier is a number, but this should honestly
 * support any type of value that can be represented as a string or used as the key
 * of an associative array.
 *
 * VERY IMPORTANT NOTE - Because of the way PHP handles the concept of "static", you need
 * to implement a static function in each class that extends dbObject  named _dbInfo(). 
 * An example object that extends dbObject would look like this:
 * <code>
 *	class Calendar extends dbObject {
 *		public static function _dbInfo() { return array("table" => 'calendar', "key" => 'calendar_id'); }
 *	
 *		public function __construct($db, $values) {
 *			$this->_types = array("calendar_id" => "int", "title" => "text", "category_id" => "int");
 *			$this->_singles[] = array('category', 'category_id', 'Calendar', 'calendar_id', true);
 *		}
 *	}
 * </code>
 * The _dbInfo function only needs to be altered by changing the values of the table and key fields
 * in the array.
 *
 * @abstract
 * @package DatabaseAbstract
 */
abstract class dbObject {
	protected $_db = NULL;
	protected static $_key = '';
	protected static $_table = '';
	protected $_mass_update = false;
	
	// Set by the user to describe the object
	protected $_types   = array();
	protected $_singles = array();
	protected $_multis  = array();

	// properties of the object
	private $_values = array();
	private $_relations = array();

	/**
	 * Constructor to set up database connection info and type enforcement.
	 *
	 * Handles all of the set up stuff itself. You should never need to touch this except perhaps to call
	 * it from a lower constructor. The stuff you need to change will be in the configure() method.
	 *
	 * @param dbDatabase $db Reference to the database object that handles all database communications.
	 * @param string $table Name of the table that this object is bound to.
	 * @param string $key_name The field that is used as the unique identifier for this object.
	 * @param array $types An associative array of the valid fields and their data types.
	 * @todo Make this enforce data types
	 */
	public function __construct($db, $values = array()) {
		// set up configuration information
		$this->configure();
	
		// Save info
		$this->_db = $db;

		// Set default values
		foreach ( $this->_types as $key => $type )
			$this->_values[$key] = '';
		
		// Load in the given values
		foreach ( $values as $key => &$value )
			if ( isset($this->_values[$key]) )
				$this->_values[$key] = $value;
		
		// set up all relationships
		foreach ( $this->_multis as $multi )
			$this->_addMultiRelation($multi[0], $multi[1], $multi[2], $multi[3], $multi[4]);
		foreach ( $this->_singles as $single )
			$this->_addSingleRelation($single[0], $single[1], $single[2], $single[3], $single[4]);
	}
	
	/**
	 * Used to describe the object that extends dbObject. Sets up the fields and types of data as well
	 * as relationships. For example, a campus that has a list of events associated with it would be
	 * defined like this:
	 * <code>
	 * public function configure() {
	 * 	$this->_types = array("campus_id" => "int",
	 * 								 "title" => "text");
	 * 	$this->_multis[] = array('events', 'campus_id', 'Event', 'event_id', 'wdlc_CampusAssign');
	 * }
	 * </code>
	 */
	protected abstract function configure();
	
	/**
	 * Generates a string representation of this object.
	 *
	 * Mostly useful for debugging, this is basically giving an array printout of the row from
	 * the database that represents this object. Does not include related objects.
	 *
	 * @return string A string representation of this object.
	 */
	public function __toString() {
		ob_start();
		echo "<pre>";
		print_r($this->_values);
		echo '</pre>';
		$data = ob_get_contents();
		ob_end_clean();
		return $data;
	}
	
	/**
	 * Call this function to add a "single" relationship, such as when an object is bound to one other object.
	 *
	 * For example, look at a database schema of this form:
	 * <pre>
	 *       Events                  Occurrences
	 *      ----------------        --------------
	 *      | event_id     |<-------| o_event_id |
	 *      | event_detail |        | a_date     |
	 *      ----------------        --------------
	 * </pre>
	 * where every occurrence belongs to a single event. Note that this is the same example as for the ONE-TO-MANY
	 * example in the dbList documentation. In this instance we need to look at the relationship from the
	 * perspective of the Occurence. As far as the Occurrence is concerned, there is only one Event that it has to
	 * relate to. It is unaware that the event may be cheating on it with other occurrences. From the Occurrence
	 * class we would have to call the following code to set up the relation:
	 * <code>
	 * $this->_singles[] = array('event', 'o_event_id', 'Event', 'event_id', true);
	 * </code>
	 * The constructor will automatically pass those parameters to this function.
	 *
	 * Then if we want to access the Event object that this occurrence belongs to, we can call it like this (assume
	 * that $OccA is an instance of Occurrence that belongs to some event):
	 * <code>
	 * echo $OccA->event;
	 * </code>
	 * This will auto-magically load the Event object with the event_id field equal to the o_event_id field in the
	 * Occurrence from the database and return it. It also keeps a reference in its local cache so that any other
	 * time afterward that you access it, it can just return it immediately. At the same time, if you would like to
	 * make the occurrence point to a different event, save $EventB, then you can call:
	 * <code>
	 * $OccA->event = $EventB;
	 * </code>
	 * This will automatically update the o_event_id field of the Occurrence object as well as the associated database
	 * row and the pointer in the Occurrence's local cache.
	 *
	 * @param string $name Name of the relationship. The field name that we will access the relationship by.
	 * @param string $local_key Name of the field in this object that we will use relate to the other object.
	 * @param string $remote_class Name of the class that the remote (related to) object will be an instance of.
	 * @param string $remote_key Name of the field in the remote object that will equal the local key field.
	 * @param string $local_link Whether this relationship value is set in this object.
	 * @return boolean True.
	 */
	protected function _addSingleRelation($name, $local_key, $remote_class, $remote_key, $local_link) {
		// Create the wrapper
		$this->_relations[$name] = new dbObjProperty($this,
		                                          $local_key,
												  $remote_class,
												  $remote_key,
												  $local_link);
		return true;
	}
	
	/**
	 * Call this function to set up a "multi" relationship (either a ONE-TO-MANY or a MANY-TO-MANY).
	 *
	 * A MANY-TO-MANY (MTM) relationships when implemented in a database requires a third table to hold the
	 * relationship information. For example, the following database schema describes an MTM relationship between
	 * Calendars and Categories:
	 * <pre>
	 *        Calendars               CategoryAssign             Categories
	 *       ---------------         -----------------          -----------------
	 *       | calendar_id |  ---->  | calendar_id   |          | category_id   |
	 *       | cal_name    |         | category_id   |   <----- | category_name |
	 *       ---------------         | is_shown      |          -----------------
	 *                               -----------------
	 * </pre>
	 * In this schema, a Calendar can be assigned to multiple Categories and a Category can have multiple calendars
	 * assigned to it. If we want to see which calendars are assigned to a specific category, we have to look at the
	 * assignment table. In our code, we would have a Calendar object and a Category object. From the Calendar object
	 * we would access the list of Categories by calling something like this:
	 * <code>
	 *	$categories = $CalA->categories->getAll();
	 *	// print list of category names and whether they are going to be shown
	 *	foreach ( $categories as $category )
	 *		echo $category['object']->category_name . " - " . $category['is_shown'];
	 * </code>
	 * This would retreive a list of all of the categories along with the properties of the relationship. The objects
	 * themselves would be inside the 'object' key of the associative array.
	 *
	 * In order to set up this relationship, we would make the following call in the object configure method:
	 * <code>
	 *	$this->_multis[] = array('categories', 'calendar_id', 'Category', 'category_id', 'CategoryAssign');
	 * </code>
	 *
	 * The other relationship that this class can be used for is a ONE-TO-MANY. For example, in the following schema,
	 * we have a ONE-TO-MANY (OTM) relationship between the Events and their Occurrences:
	 * <pre>
	 *       Events                  Occurrences
	 *      ----------------        --------------
	 *      | event_id     |<-------| o_event_id |
	 *      | event_detail |        | a_date     |
	 *      ----------------        --------------
	 * </pre>
	 * From the Event class we would be looking at a list of multiple Occurrences, and we would set up the relationship
	 * like this:
	 * <code>
	 *	$this->_multis[] = array('occurrences', 'event_id', 'Occurrence', 'o_event_id');
	 * </code>
	 * Note that the major difference between the initiation for the ONE-TO-MANY relationship and the MANY-TO-MANY is
	 * that there is no associative table in the ONE-TO-MANY one. Beyond that, accessing the entries is exactly the 
	 * same. For example, to display a list of all of the occurrence dates associated with Event object eventA:
	 * <code>
	 *	$occurrences = $eventA->occurrences->getAll();
	 *	foreach ( $occurrences as $occurrence )
	 *		echo $occurrence->a_date;
	 * </code>
	 * If you wanted to add another occurrence, for example $OccB, then you could call:
	 * <code>
	 *	$eventA->occurrences->add($OccB);
	 * </code>
	 *
	 * It is important to note that in this example, we are talking about how to implement the Event class. From
	 * the perspective of the Occurrence class, this is a simple "single" relationship and will be implemented
	 * as described in the _addSingleRelation documentation.
	 *
	 * @param string $name Name of the relationship. The field name that we will access the relationship by.
	 * @param string $local_key Name of the field in this object that we will use relate to the other object.
	 * @param string $remote_class Name of the class that the remote (related to) object will be an instance of.
	 * @param string $remote_key Name of the field in the remote object that will equal the local key field.
	 * @param string $assoc_table Name of the table that contains the relationship information.
	 */
	protected function _addMultiRelation($name, $local_key, $remote_class, $remote_key, $assoc_table='') {
		// Create the wrapper
		$this->_relations[$name] = new dbList($this->_db, $assoc_table, $local_key, $this, $remote_key, $remote_class);
	}
	
	/**
	 * Provides database wrapped access to the values of the object.
	 *
	 * This is a magic PHP function and it allows us to control all of the data going in and out of this object.
	 * Whenever the user tries to access a property, for example to read "$cal->calendar_id", this function will
	 * be called. This allows us to make relationship properties appear as local properties as well as to cache
	 * them so we can retrieve them on demand, or ignore them if we don't end up needing them.
	 *
	 * @param string $name The name of the property we are trying to access
	 * @return mixed Value of the property accessed.

	 */
	public function __get($name) {
		// Handles loading a dbObjProperty one-to-one relation
		if ( $this->_relations[$name] instanceof dbObjProperty ) {
			// Load the object value if not already loaded
			if ( !$this->_relations[$name]->isInit() ) {
				// Load up the variables needed for referencing
				$class_name = $this->_relations[$name]->getClass();
				$local_key  = $this->_relations[$name]->getKey();
				$local_value = $this->_values[$local_key];

				// Load the object from the database
				$this->_relations[$name]->set($this->_db->getObject($class_name, $local_value));
				
				// If it wasn't a valid link, then unset the local link
				if ( !$this->_relations[$name]->get() instanceof dbObject ) {
					// set the value of the object to be a 0 (no entry)
					$this->__set($local_key, NULL);
				}
			}
			
			// return reference to the object
			return $this->_relations[$name]->get();
		}
		
		// Handles multi-entry relationships
		else if ( $this->_relations[$name] instanceof dbList ) {
			return $this->_relations[$name];
		}

		// Or if it's a basic value, return it directly
		else if ( isset($this->_values[$name]) )
			return $this->_values[$name];

		// Or if it's not set, return NULL
		else
			return NULL;
	}

	/**
	 * Database wrapped access method to write values into this object.
	 *
	 * A PHP magic method that allows us to control any attempts to write data into our
	 * object. For example, when we set the title of a Calendar object by calling:
	 * <code>
	 *	$cal->title = "test title";
	 * </code>
	 * this method will be called with the first paramter being set to "title" for the name
	 * of the property and the second paramter to "test title" for the value we are trying
	 * to set it to. This works the same for when we assign objects, etc. This function is
	 * usually not called directly.
	 *
	 * @param string $name Name of the property being set.
	 * @param mixed $value The value being assigned to
	 */	
	public function __set($name, $value) {
		// Validate input
		if ( !isset($this->_types[$name]) && !isset($this->_relations[$name]) )
			throw new Exception("$name not a valid field of ".get_class($this));
		if ( $this->_relations[$name] instanceof dbObject && !$value instanceof dbObject )
			throw new Exception("Formatting error, \$value not a dbObject");

		// Save the value
		if ( $this->_relations[$name] instanceof dbObjProperty ) {
			// Save the object value
			$this->_relations[$name]->set($value);
			
			// Update the local variable the mark the change
			$key = $this->_relations[$name]->getKey();
			if ( $key ) {
				$this->_values[$key] = $this->_relations[$name]->getKeyValue();
			
				// Update the database
				$this->_db->putObject($this, $key);
			}
		} else {
			$this->_values[$name] = $value;
		
			// Save to database
			$this->_db->putObject($this, $name);
		}
	}

	/**
	 * Only to be called by the dbDatabase object. Used to clean up relationships when
	 * we are deleting an object.
	 */
	public function _delRelations() {
		foreach ( $this->_relations as $key => &$relation ) {
			// Remove a "single" relationship
			if ( $relation instanceof dbObjProperty ) {
				$this->__set($relation->getKey(), 0);
				unset($this->_relations[$key]);
			}
			
			// Remove a "multi" relationship
			else {
				// Get list of all of the relatives
				$relatives = $relation->getAll();
				
				// Remove each
				foreach ( $relatives as $relative )
					$relation->remove($relative['object']);
				
				// Remove the relationship itself
				unset($this->_relations[$key]);
			}
		}
	}

	/**
	 * Get the value associated with the unioue identifier for this object
	 *
	 * @return integer The identifying value of this object
	 */
	public function getKeyValue() {
		$info = $this->_dbInfo();
		return $this->_values[$info['key']];
	}
	
	/**
	 * Get the name of the field used to identify this type of object uniquely.
	 *
	 * @return string The name of the unique field.
	 */
	public function getKey() {
		$info = $this->_dbInfo();
		return $info['key'];
	}
	
	/**
	 * Get the name of the table that this object is linked with in the database.
	 *
	 * @return string Name of the table object is linked to
	 */
	public function getTable() {
		$info = $this->_dbInfo();
		return $info['table'];
	}

	/**
	 * Gets the associative array of all of the values this object has. This is the basic
	 * values only, not the objects it is linking to, etc. Used for database updates.
	 *
	 * @return array Associative array of all values.
	 */
	public function _getValues() {
		return $this->_values;
	}
}

/**
 * A wrapper for linking objects in "single" relationships.
 *
 * This describes the relationship between two objects in a ONE-TO-ONE relationship. It is the
 * equivalent of the dbList class and is used in basically the same way.
 *
 * @package DatabaseAbstract
 */
class dbObjProperty {
	protected $_remote_obj = NULL;
	protected $_local_obj  = NULL;
	protected $_remote_key = '';
	protected $_remote_class = '';
	protected $_local_key  = '';
	protected $_linked_local = false;
	
	private $_is_set = false;
	
	/**
	 * Sets up the new ONE-TO-ONE relationship. Not used directly by the user.
	 *
	 * @param dbObject $owner Reference to the object that this is linking from
	 * @param string $local_key Name of the field in this object that we will use relate to the other object.
	 * @param string $remote_class Name of the class that the remote (related to) object will be an instance of.
	 * @param string $remote_key Name of the field in the remote object that will equal the local key field.
	 * @param string $local_link Whether this relationship value is set in this object.
	 */
	public function __construct($owner, $local_key, $remote_class, $remote_key, $local_link) {
		// Set property options
		$this->_local_obj  = $owner;
		$this->_local_key  = $local_key;
		$this->_remote_class = $remote_class;
		$this->_remote_key = $remote_key;
		$this->_linked_local = $local_link;
	}
	
	/**
	 * Sets the object being pointed to.
	 *
	 * @param dbObject $remote_obj Reference to the object this relationship should be pointing to.
	 */
	public function set($remote_obj) {
		// Validate input
		if ( !$remote_obj instanceof dbObject && !is_null($remote_obj) )
			throw new Exception("Remote Object not instance of dbObject!");
		
		// Save the remote object
		$this->_remote_obj = $remote_obj;
		
		// Set flag
		if ( is_null($remote_obj) )
			$this->_is_set = false;
		else
			$this->_is_set = true;
	}
	
	/**
	 * Checks if we have retrieved the object being pointed to from the database yet. We only do that when
	 * it is accessed.
	 *
	 * @return boolean True if we have retrieved the object from the database, false otherwise.
	 */
	public function isInit() {
		return $this->_is_set;
	}
	
	/**
	 * Unlink from an object. Used to reset the relationship.
	 */
	public function clear() {
		$this->_remote_obj = NULL;
		$this->_is_set = false;
	}
	
	/**
	 * Retrieve the object being related to.
	 *
	 * @return dbObject Reference to the object we are pointing to in this relationship
	 */
	public function get() { return $this->_remote_obj; }
	
	/**
	 * Gets the name of the class type this relationship points to.
	 *
	 * @return string Name of the class type this relates to
	 */
	public function getClass() { return $this->_remote_class; }

	/**
	 * Get the field name of the owner that we are using to make the link
	 *
	 * @return string Name of the field of the owner
	 */
	public function getKey() {
		if ( $this->_linked_local )
			return $this->_local_key;
		else
			return false;
	}

	/**
	 * Get the value of the key field from the owner
	 *
	 * @return integer Value of the key field.
	 */
	public function getKeyValue() {
		$key = $this->_remote_key;
		if ( is_null($this->_remote_obj) )
			return 0;
		else
			return $this->_remote_obj->$key;
	}
}

?>