<?php
/**
 * OpenEyes
 *
 * (C) Moorfields Eye Hospital NHS Foundation Trust, 2008-2011
 * (C) OpenEyes Foundation, 2011-2013
 * This file is part of OpenEyes.
 * OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @package OpenEyes
 * @link http://www.openeyes.org.uk
 * @author OpenEyes <info@openeyes.org.uk>
 * @copyright Copyright (c) 2008-2011, Moorfields Eye Hospital NHS Foundation Trust
 * @copyright Copyright (c) 2011-2013, OpenEyes Foundation
 * @license http://www.gnu.org/licenses/gpl-3.0.html The GNU General Public License V3.0
 */

//Currently failing as BaseElement doesn't have a db table. Suspect that this file needs replacing
class InitialDbMigrationCommandTest extends CTestCase
{
	protected $initialDbMigrationCommand;
	protected $fileNameRegEx;
	protected $oeMigration;
	protected $filesCreated;
	protected $foldersCreated;

	public function setUp(){
		$this->oeMigration = new OEMigration();
		$this->initialDbMigrationCommand = new InitialDbMigrationCommand('initialdbmigration', null);
		$this->fileNameRegEx = '|^m\d{6}_\d{6}_[a-z]*$|i';
		$this->filesCreated = array();
		$this->foldersCreated = array();
	}

	public function testRunSuccessful()
	{
		$initDbMigrationResult = $this->initialDbMigrationCommand->run();
		$this->assertInstanceOf('InitialDbMigrationResult' , $initDbMigrationResult, 'Not and instance of InitialDbMigrationResult' );
		$this->assertTrue($initDbMigrationResult->result === true);
		$this->assertRegExp($this->fileNameRegEx , $initDbMigrationResult->fileName );
		$this->assertInternalType('array' , $initDbMigrationResult->tables );
		$this->assertGreaterThan(0 , count($initDbMigrationResult->tables));
		$thisMigrationFile = $this->oeMigration->getMigrationPath()
			. DIRECTORY_SEPARATOR . $initDbMigrationResult->fileName . '.php';
		$this->assertFileExists($thisMigrationFile);

		//make sure migration table is excluded -
		$fileCnt = file_get_contents($thisMigrationFile);
		$migrationTableStrings = substr_count($fileCnt , 'tbl_migration');
		$this->assertEquals(3 , $migrationTableStrings);

		$migrationDataFolder =  $this->oeMigration->getMigrationPath()
			. DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . $initDbMigrationResult->fileName;

		$migrDataFile = $migrationDataFolder . DIRECTORY_SEPARATOR . '01_tbl_migration.csv';
		$this->assertFileNotExists($migrDataFile);

		//test file is valid php and functions exist
		include $thisMigrationFile;
		$this->assertTrue(class_exists($initDbMigrationResult->fileName));
		$thisMigrationClassMethods = get_class_methods($initDbMigrationResult->fileName );
		$this->assertContains('up', $thisMigrationClassMethods);
		$this->assertContains('up', $thisMigrationClassMethods);
		$this->assertContains('down', $thisMigrationClassMethods);
		$this->assertContains('safeUp', $thisMigrationClassMethods);
		$this->assertContains('safeDown', $thisMigrationClassMethods);

		//make sure we keep track of files created
		$this->filesCreated[]= $thisMigrationFile;
		$this->filesCreated[]= $migrDataFile;
		$this->foldersCreated[]=$migrationDataFolder;
	}

	/**
	 *  InitialDbMigrationCommandException
	 */
	public function testRunMigrationFolderNotAccessible(){
		$this->setExpectedException('InitialDbMigrationCommandException','Migration folder is not writable/accessible');
		$this->initialDbMigrationCommand->oeMigration = new OEMigration();
		$this->initialDbMigrationCommand->oeMigration->setMigrationPath('/root');
		$this->initialDbMigrationCommand->run();
	}

	public function testRunMigrationNoTables(){
		$mockSchema  = $this->getMockBuilder('CMysqlSchema')
			->disableOriginalConstructor()
			->getMock();

		$mockSchema->expects( $this->any() )->method('getTableNames')->will($this->returnValue(array()));
		$mockSchema->expects( $this->any() )->method('loadTable')->will($this->returnValue(null));
		$this->initialDbMigrationCommand->setDbSchema($mockSchema);

		$this->setExpectedException('InitialDbMigrationCommandException','No tables to export in the current database');
		$this->initialDbMigrationCommand->run();
	}
	/**
	 * @description test the getTemplate returns an object that can be stringyfied
	 * into a representation of a migration file to be dymamically filled
	 */
	public function testGetTemplate(){
		$expected = <<<'EOD'
<?php

	class {ClassName} extends OEMigration
	{

		public function up(){
			// Check for existing migrations
			$existing_migrations = $this->getDbConnection()->createCommand("SELECT count(version) FROM `tbl_migration`")->queryScalar();
			if ($existing_migrations == 1) {
				$this->createTables();
			} else {
				// Database has existing migrations, so check that last migration step to be consolidated was applied
				$previous_migration = $this->getDbConnection()->createCommand("SELECT * FROM `tbl_migration` WHERE version = '{LastMigration}'")->execute();
				if ($previous_migration) {
					// Previous migration was applied, safe to consolidate
					echo "Consolidating old migration data";
					$this->execute("DELETE FROM `tbl_migration` WHERE version < '{ClassName}'");
				} else {
					// Database is not migrated up to the consolidation point, cannot migrate
					echo "Previous migrations missing or incomplete, migration not possible\n";
					return false;
				}
			}
		}

		{ClassCreateTables}

		public function down()
		{
			echo "{ClassName} does not support migration down.\n";
			return false;
		}


		// Use safeUp/safeDown to do migration with transaction
		public function safeUp()
		{
			$this->up();
		}

		public function safeDown()
		{
			$this->down();
		}

	}
EOD;
		$this->assertEquals($expected, $this->initialDbMigrationCommand->getTemplate(), 'Template was not returned correctly');
	}

	public function tearDown(){
		foreach($this->filesCreated as $file){
			@unlink($file);
		}
		foreach($this->foldersCreated as $folder){
			if(is_dir($folder)){
				if ($dh = opendir($folder)) {
					$files = scandir($folder);
					foreach ($files  as $file) {
						if($file != '.' && $file != '..' && is_file($folder . DIRECTORY_SEPARATOR . $file)){
							$fullFilePath = $folder . DIRECTORY_SEPARATOR . $file ;
							$fileRemoved = unlink($fullFilePath);
							if(!$fileRemoved)
								echo "\nCould not remove : " .$fullFilePath;
						}
					}
					closedir($dh);
				}
			}
			rmdir($folder);
		}
		unset($this->initialDbMigrationCommand);
	}

}

