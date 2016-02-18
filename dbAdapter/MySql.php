<?
/**
 * A default MySql adapter for using the OOdb system with a MySQL backend.
 *
 * @author Justin DeMaris <justin.demaris@gmail.com>
 * @package DatabaseAbstract
 * @version 0.5.0-beta
 */
 
/**
 * A MySQL adapter for the OOdb system.
 *
 * @package DatabaseAbstract
 */
class dbAdapter_MySql extends dbAdapter {
	protected $_db = NULL;

	protected function _connect() {
		// Create pointer to the connection info for readable code
		$inf =& $this->_connection_info;
		
		// Connect to the database server
		$this->_db = mysql_connect($inf['server'],
		                           $inf['username'],
								   $inf['password']);
		
		// Select the database to work on
		mysql_select_db($inf['database'], $this->_db);
		
		// Set Flag
		$this->_is_connected = true;
	}

	public function runQuery($query) {
		// Make sure we're connected
		if ( !$this->_is_connected )
			$this->_connect();

		// Run the query
		$results = mysql_query($query, $this->_db);
		
		// If we're debugging
		if ( defined("__OODB_DEBUG__") && __OODB_DEBUG__ ) {
			echo mysql_error($this->_db);
			echo "<p><b>Running Query: </b>$query</p>\n";
			echo "<p><b>Rows Affected: </b>".mysql_affected_rows($this->_db)."</p>\n";
		}

		// Convert the results to an array if it was a result
		if ( $results === false ) return false;
		if ( $results === true  ) return mysql_insert_id($this->_db);
		else {
			$rows = array();
			while ( $row = mysql_fetch_assoc($results) )
				$rows[] = $row;
			return $rows;
		}
	}
	
	public function escape($value) { return mysql_escape_string($value); }
	
	public function getRow($table, $key, $value) {
		// Protect against SQL injection
		$value = mysql_escape_string($value);
		
		// Generate the query
		$query = "SELECT * FROM $table WHERE $key='$value' LIMIT 1";
		
		// Get the row from the table
		$result = $this->runQuery($query);
		
		// Convert the row into an array
		if ( count($result) == 0 )
			return false;						// empty array for empty result
		else
			return $result[0];					// one row for one entry
	}

	public function newRow($table, $row) {
		// If we were given a row of data, then use it to create the row
		if ( is_array($row)) {
			// Protect against injection
			foreach ( $row as $key => $value )
				$row[$key] = mysql_escape_string($value);
			
			// Generate the query
			$keys = implode('`,`', array_keys($row));
			$values = implode("','", array_values($row));
			$query = "INSERT INTO `$table` (`$keys`) VALUES ('$values')";
		}
		
		// Otherwise, we were given a key field, so use it to create a blank row
		else {
			$query = "INSERT INTO `$table` (`$row`) VALUES (NULL)";
		}
		
		// Run the query
		$this->runQuery($query);

		// Obtain an AUTO-INCREMENT result (0 if none)
		return mysql_insert_id($this->_db);
	}
	
	public function putRow($table, $key, $row) {
		// Validate input
		if ( !is_array($row) || !isset($row[$key]) )
			return false;
		
		// Generate the query
		$key_value = mysql_escape_string($row[$key]);
		unset($row[$key]);
		foreach ( $row as $ind => $value )
			$clauses[] = "`$ind`='".mysql_escape_string($value)."'";
		$query = "UPDATE `$table` SET ".implode(",", $clauses)." WHERE `$key`='$key_value'";;
		
		// Run the query
		$this->runQuery($query);
	}

	public function delRow($table, $key, $value) {
		// Protect against injection
		$value = mysql_escape_string($value);
		
		// Generate the query
		$query = "DELETE FROM `$table` WHERE `$key`='$value'";
		
		// Run the query
		$this->runQuery($query);
	}

	public function getRows($table, $params = array()) {
		// Base query
		$query = "SELECT * FROM `$table`";

		// If there are restrictions, generate the clauses
		if ( count($params) > 0 ) {
			foreach ( $params as $key => $value )
				$clauses[] = "`$key`='".mysql_escape_string($value)."'";
			$query .= " WHERE ".implode(" AND ", $clauses);
		}
		
		// Run the query
		$results = $this->runQuery($query);
		
		// Location to store the results in
		$data = array();
		
		// Convert the results to an array
		foreach ( $results as $row )
			$data[] = $row;
		
		// Return the data
		return $data;
	}
	
	public function delRows($table, $params) {
		// Base query
		$query = "DELETE FROM `$table`";
		
		// If there are restrictions, generate the clauses
		if ( count($params) > 0 ) {
			foreach ( $params as $key => $value )
				$clauses[] = "`$key`='".mysql_escape_string($value)."'";
			$query .= " WHERE ".implode(" AND ", $clauses);
		}
		
		// Run the query & return number of rows deleted
		$this->runQuery($query);
		return mysql_affected_rows($this->_db);
	}
	
	public function putRows($table, $params, $values) {
		// Base query
		$query = "UPDATE `$table`";
		
		// Create list of changes
		foreach ( $values as $key => $value )
			$clauses[] = "`$key`='".mysql_escape_string($value)."'";
		$changes = implode(",", $clauses);
			
		// Create list of restrictions
		$clauses = array();
		foreach ( $params as $key => $value )
			$clauses[] = "`$key`='".mysql_escape_string($value)."'";
		$restrictions = implode(" AND ", $clauses);
		
		// Compile and run the query
		$query .= " SET $changes WHERE $restrictions";
		$this->ruNQuery($query);
		
		// Return the number of affected changes
		return mysql_affected_rows($this->_db);
	}
}

?>