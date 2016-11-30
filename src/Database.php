<?php
final class Model_Database extends OsyLib_Database_MySQL {
	private static
		$connection;

	public static function get() {
		if(is_null(self::$connection)) {
			self::$connection = new self(Configuration::DATABASE_HOST, Configuration::DATABASE_USER, Configuration::DATABASE_PASSWORD);
			self::$connection->connect();

			if(!self::$connection->link->autocommit(false)) {
				throw new Exception('DatabaseError::could not set autocommit');
			}

			if(!self::$connection->link->set_charset('utf8')) {
				throw new Exception('DatabaseError::could not set charset to utf8');
			}
		}

		return self::$connection;
	}
}
