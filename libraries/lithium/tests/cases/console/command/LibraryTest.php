<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright     Copyright 2010, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace lithium\tests\cases\console\command;

use \Phar;
use \lithium\console\command\Library;
use \lithium\core\Libraries;
use \lithium\console\Request;

class LibraryTest extends \lithium\test\Unit {

	public $request;

	protected $_backup = array();

	protected $_testPath = null;

	public function skip() {
		$this->_testPath = LITHIUM_APP_PATH . '/resources/tmp/tests';
		$this->skipIf(!is_writable($this->_testPath), "{$this->_testPath} is not writable.");
	}

	public function setUp() {
		$this->_backup['cwd'] = getcwd();
		$this->_backup['_SERVER'] = $_SERVER;
		$_SERVER['argv'] = array();

		chdir($this->_testPath);

		Libraries::add('library_test', array(
			'path' => $this->_testPath . '/library_test', 'bootstrap' => false
		));

		Libraries::add('library_test_plugin', array(
			'path' => $this->_testPath . '/library_test_plugin'
		));

		$this->classes = array(
			'service' => '\lithium\tests\mocks\console\command\MockLibraryService',
			'response' => '\lithium\tests\mocks\console\MockResponse'
		);
		$this->request = new Request(array('input' => fopen('php://temp', 'w+')));
		$this->library = new Library(array(
			'request' => $this->request, 'classes' => $this->classes
		));
		$this->testConf = $this->library->conf = $this->_testPath . '/library.json';
	}

	public function tearDown() {
		$_SERVER = $this->_backup['_SERVER'];
		chdir($this->_backup['cwd']);
		Libraries::remove('library_test');
		unset($this->library, $this->request);
	}

	public function testConfigServer() {
		$result = $this->library->config('server', 'lab.lithify.me');
		$this->assertTrue($result);

		$expected = array('servers' => array(
			'lab.lithify.me' => true
		));
		$result = json_decode(file_get_contents($this->testConf), true);
		$this->assertEqual($expected, $result);

		//create a new object to test initialiaztion
		$this->request->params += array('conf' => $this->testConf);
		$library = new Library(array(
			'request' => $this->request, 'classes' => $this->classes
		));

		$expected = array('servers' => array(
			'lab.lithify.me' => true
		));
		$result = $this->library->config();
		$this->assertEqual($expected, $result);
	}

	public function testExtract() {
		$this->skipIf(!extension_loaded('zlib'), 'The zlib extension is not loaded.');
		$this->library->library = 'library_test';

		$expected = true;
		$result = $this->library->extract($this->_testPath . '/library_test');
		$this->assertEqual($expected, $result);

		$expected = "library_test created in {$this->_testPath} from ";
		$expected .= realpath(LITHIUM_LIBRARY_PATH)
			. "/lithium/console/command/create/template/app.phar.gz\n";
		$result = $this->library->response->output;
		$this->assertEqual($expected, $result);
	}

	public function testArchive() {
		$this->skipIf(!extension_loaded('zlib'), 'The zlib extension is not loaded.');
		$this->skipIf(
			ini_get('phar.readonly') == '1',
			'Skipped test {:class}::{:function}() - INI setting phar.readonly = On'
		);

		$this->library->library = 'library_test';

		$expected = true;
		$testPath = "{$this->_testPath}/library_test";
		$result = $this->library->archive($testPath, $testPath);
		$this->assertEqual($expected, $result);

		$expected = "library_test.phar.gz created in {$this->_testPath} from ";
		$expected .= "{$this->_testPath}/library_test\n";
		$result = $this->library->response->output;
		$this->assertEqual($expected, $result);

		Phar::unlinkArchive("{$this->_testPath}/library_test.phar");
	}

	public function testExtractWithFullPaths() {
		$this->skipIf(
			!file_exists("{$this->_testPath}/library_test.phar.gz"),
			'Skipped test {:class}::{:function}() - depends on {:class}::testArchive()'
		);
		$this->library->library = 'library_test';

		$expected = true;
		$result = $this->library->extract(
			$this->_testPath . '/library_test.phar.gz', $this->_testPath . '/new'
		);
		$this->assertEqual($expected, $result);

		$this->assertTrue(file_exists($this->_testPath . '/new'));

		$expected = "new created in {$this->_testPath} from ";
		$expected .= "{$this->_testPath}/library_test.phar.gz\n";
		$result = $this->library->response->output;
		$this->assertEqual($expected, $result);

		$result = file_exists($this->_testPath . '/new/.htaccess');
		$this->assertTrue($result);

		$result = file_exists($this->_testPath . '/new/.DS_Store');
		$this->assertFalse($result);

		Phar::unlinkArchive($this->_testPath . '/library_test.phar.gz');
	}

	public function testArchiveNoLibrary() {
		$this->skipIf(!extension_loaded('zlib'), 'The zlib extension is not loaded.');
		$this->skipIf(
			ini_get('phar.readonly') == '1',
			'Skipped test {:class}::{:function}() - INI setting phar.readonly = On'
		);

		chdir('new');
		$app = new Library(array(
			'request' => new Request(), 'classes' => $this->classes
		));
		$app->library = 'does_not_exist';

		$expected = true;
		$result = $app->archive();
		$this->assertEqual($expected, $result);

		$expected = "new.phar.gz created in {$this->_testPath} from ";
		$expected .= "{$this->_testPath}/new\n";
		$result = $app->response->output;
		$this->assertEqual($expected, $result);

		Phar::unlinkArchive($this->_testPath . '/new.phar');
		Phar::unlinkArchive($this->_testPath . '/new.phar.gz');
		$this->_cleanUp('tests/new');
		rmdir($this->_testPath . '/new');
	}

	public function testExtractWhenLibraryDoesNotExist() {
		$this->skipIf(!extension_loaded('zlib'), 'The zlib extension is not loaded.');
		chdir($this->_testPath);
		$app = new Library(array(
			'request' => new Request(), 'classes' => $this->classes
		));
		$app->library = 'does_not_exist';

		$expected = true;
		$result = $app->extract();
		$this->assertEqual($expected, $result);

		$this->assertTrue(file_exists($this->_testPath . '/new'));

		$expected = "new created in {$this->_testPath} from ";
		$expected .= realpath(LITHIUM_LIBRARY_PATH)
			. "/lithium/console/command/create/template/app.phar.gz\n";
		$result = $app->response->output;
		$this->assertEqual($expected, $result);

		$this->_cleanUp();
	}

	public function testExtractPlugin() {
		$this->skipIf(!extension_loaded('zlib'), 'The zlib extension is not loaded.');
		$this->library->library = 'library_plugin_test';
		$path = $this->_testPath;

		$expected = true;
		$result = $this->library->extract('plugin', "{$path}/library_test_plugin");
		$this->assertEqual($expected, $result);

		$expected = "library_test_plugin created in {$path} from " . realpath(LITHIUM_LIBRARY_PATH);
		$expected .= "/lithium/console/command/create/template/plugin.phar.gz\n";
		$result = $this->library->response->output;
		$this->assertEqual($expected, $result);

		$this->_cleanup();
	}

	public function testFormulate() {
		$this->library->formulate();
		$expected = '/please supply a name/';
		$result = $this->library->response->output;
		$this->assertPattern($expected, $result);

		$path = $this->_testPath . '/library_test_plugin';
		mkdir($path);
		$result = $this->library->formulate($path);
		$this->assertTrue($result);

		$result = file_exists($path . '/config/library_test_plugin.json');
		$this->assertTrue($result);

		$this->_cleanUp();
	}

	public function testFormulateWithFormula() {
		$path = $this->_testPath . '/library_test_plugin';
		mkdir($path);
		mkdir($path . '/config');
		file_put_contents(
			$path . '/config/library_test_plugin.json',
			json_encode(array(
				'name' => 'library_test_plugin',
				'version' => '1.0',
				'summary' => 'something',
				'sources' => array(
					'phar' => 'http://somewhere.com/download/library_test_plugin.phar.gz'
				)
			))
		);

		$result = $this->library->formulate($path);
		$this->assertTrue($result);

		$result = file_exists($path . '/config/library_test_plugin.json');
		$this->assertTrue($result);
	}

	public function testNoFormulate() {
		$path = $this->_testPath . '/library_test_no_plugin';
		$result = $this->library->formulate($path);
		$this->assertFalse($result);

		$result = file_exists($path . '/config/library_test_no_plugin.json');
		$this->assertFalse($result);

		$expected = '/Formula for library_test_no_plugin not created/';
		$result = $this->library->response->error;
		$this->assertPattern($expected, $result);
	}

	public function testFormulateNoPath() {
		$path = $this->_testPath . '/library_test_no_plugin';
		umask(0);
		mkdir($path, 655);
		umask(100);
		$this->expectException('/Permission denied/');

		$result = $this->library->formulate($path);
		$this->assertFalse($result);

		$result = file_exists($path . '/config/library_test_plugin.json');
		$this->assertFalse($result);

		$expected = '/Formula for library_test_no_plugin not created/';
		$result = $this->library->response->error;
		$this->assertPattern($expected, $result);

		umask(0);
		rmdir($path);
	}

	public function testPushNoName() {
		$this->library->push();
		$expected = 'please supply a name';
		$result = $this->library->response->output;
		$this->assertTrue($result);
	}

	public function testPush() {
		$this->skipIf(!extension_loaded('zlib'), 'The zlib extension is not loaded.');
		$this->skipIf(
			ini_get('phar.readonly') == '1',
			'Skipped test {:class}::{:function}() - INI setting phar.readonly = On'
		);

		$result = file_put_contents(
			$this->_testPath . '/library_test_plugin/config/library_test_plugin.json',
			json_encode(array(
				'name' => 'library_test_plugin',
				'version' => '1.0',
				'summary' => 'something',
				'sources' => array(
					'phar' => 'http://somewhere.com/download/library_test_plugin.phar.gz'
				)
			))
		);
		$this->assertTrue($result);

		$result = $this->library->archive(
			$this->_testPath . '/library_test_plugin',
			$this->_testPath . '/library_test_plugin'
		);
		$this->assertTrue($result);

		$expected = "library_test_plugin.phar.gz created in {$this->_testPath} from ";
		$expected .= "{$this->_testPath}/library_test_plugin\n";
		$result = $this->library->response->output;
		$this->assertEqual($expected, $result);

		$result = file_exists($this->_testPath . '/library_test_plugin.phar.gz');
		$this->assertTrue($result);
		$this->library->response->output = null;

		$result = $this->library->push('library_test_plugin');
		$this->assertTrue($result);

		$expected = "library_test_plugin added to {$this->library->server}.\n";
		$expected .= "See http://{$this->library->server}/lab/plugins/view/{$result->id}\n";
		$result = $this->library->response->output;
		$this->assertEqual($expected, $result);

		$result = is_dir($this->_testPath . '/library_test_plugin');
		$this->assertTrue($result);

		$this->_cleanUp('tests/library_test_plugin');
		rmdir($this->_testPath . '/library_test_plugin');
	}

	public function testInstall() {
		$this->skipIf(!extension_loaded('zlib'), 'The zlib extension is not loaded.');
		$this->skipIf(
			ini_get('phar.readonly') == '1',
			'Skipped test {:class}::{:function}() - relies on {:class}::testPush()'
		);
		$this->library->path = $this->_testPath;
		$result = $this->library->install('library_test_plugin');
		$this->assertTrue($result);

		$result = file_exists($this->_testPath . '/library_test_plugin.phar.gz');
		$this->assertTrue($result);

		$result = is_dir($this->_testPath . '/library_test_plugin');
		$this->assertTrue($result);

		Phar::unlinkArchive($this->_testPath . '/library_test_plugin.phar');
		Phar::unlinkArchive($this->_testPath . '/library_test_plugin.phar.gz');
		$this->_cleanUp();
	}

	public function testNoInstall() {
		$result = $this->library->install('library_test_plugin');
		$expected = "library_test_plugin not installed.\n";
		$result = $this->library->response->output;
		$this->assertEqual($expected, $result);
		$this->library->response->output = null;

		$this->request->params += array('server' => null);
		$library = new Library(array(
			'request' => $this->request, 'classes' => $this->classes
		));
		$library->conf = $this->testConf;
		$library->config('server', 'localhost');
		$result = $this->library->install('library_not_a_plugin');
		$expected = "library_not_a_plugin not found.\n";
		$result = $this->library->response->error;
		$this->assertEqual($expected, $result);
	}

	public function testNoInstalLab() {
		$this->skipIf(!extension_loaded('zlib'), 'The zlib extension is not loaded.');
		$this->skipIf(
			ini_get('phar.readonly') == '1',
			'Skipped test {:class}::{:function}() - relies on {:class}::testPush()'
		);
		$this->library->path = $this->_testPath;
		$result = $this->library->install('li3_lab');

		$expected = "li3_lab not installed.\n";
		$result = $this->library->response->output;
		$this->assertEqual($expected, $result);

		$result = is_dir($this->_testPath . '/li3_lab');
		$this->assertFalse($result);
		$this->_cleanUp();
	}

	public function testInstallDocs() {
		$this->skipIf(strpos(shell_exec('git --version'), 'git version') === false,
			'The git is not installed.'
		);
		$this->skipIf(dns_check_record("google.com") === false, "No internet connection.");

		$this->library->path = $this->_testPath;
		$result = $this->library->install('li3_docs');
		$this->assertTrue($result);

		$result = is_dir($this->_testPath . '/li3_docs');
		$this->assertTrue($result);
		$this->_cleanUp();
	}

	public function testFind() {
		$this->library->find();

$expected = <<<'test'
--------------------------------------------------------------------------------
lab.lithify.me > li3_lab
--------------------------------------------------------------------------------
the li3 plugin client/server
Version: 1.0
Created: 2009-11-30
--------------------------------------------------------------------------------
lab.lithify.me > library_test_plugin
--------------------------------------------------------------------------------
an li3 plugin example
Version: 1.0
Created: 2009-11-30

test;
	}

	public function testFindNotFound() {
		$this->request->params += array('server' => null);
		$library = new Library(array(
			'request' => $this->request, 'classes' => $this->classes
		));
		$library->conf = $this->testConf;
		$library->config('server', 'localhost');
		$library->find();
		$expected = "No plugins at localhost\n";
		$result = $library->response->output;
		$this->assertEqual($expected, $result);
	}

	public function testForceArchive() {
		$this->skipIf(!extension_loaded('zlib'), 'The zlib extension is not loaded.');
		$this->skipIf(
			ini_get('phar.readonly') == '1',
			'Skipped test {:class}::{:function}() - INI setting phar.readonly = On'
		);
		$result = $this->library->extract('plugin', $this->_testPath . '/library_test_plugin');
		$this->assertTrue($result);

		$this->library->response->output = null;
		$result = $this->library->archive(
			$this->_testPath . '/library_test_plugin',
			$this->_testPath . '/library_test_plugin'
		);
		$this->assertTrue($result);

		$expected = "library_test_plugin.phar.gz created in {$this->_testPath} from ";
		$expected .= "{$this->_testPath}/library_test_plugin\n";
		$result = $this->library->response->output;
		$this->assertEqual($expected, $result);

		$this->library->response->output = null;
		$result = $this->library->archive(
			$this->_testPath . '/library_test_plugin',
			$this->_testPath . '/library_test_plugin'
		);
		$this->assertFalse($result);

		$expected = "library_test_plugin.phar already exists in {$this->_testPath}\n";
		$result = $this->library->response->error;
		$this->assertEqual($expected, $result);


		$this->library->force = true;
		$this->library->response->output = null;
		$result = $this->library->archive(
			$this->_testPath . '/library_test_plugin',
			$this->_testPath . '/library_test_plugin'
		);
		$this->assertTrue($result);

		$expected = "library_test_plugin.phar.gz created in {$this->_testPath} from ";
		$expected .= "{$this->_testPath}/library_test_plugin\n";
		$result = $this->library->response->output;
		$this->assertEqual($expected, $result);

		unlink($this->_testPath . '/library_test_plugin.phar');

		$this->library->force = false;
		$this->library->response->output = null;
		$this->library->response->error = null;
		$result = $this->library->archive(
			$this->_testPath . '/library_test_plugin',
			$this->_testPath . '/library_test_plugin'
		);
		$this->assertFalse($result);

		$expected = "library_test_plugin.phar.gz already exists in {$this->_testPath}\n";
		$result = $this->library->response->error;
		$this->assertEqual($expected, $result);

		$this->library->force = true;
		$this->library->response->output = null;
		$this->library->response->error = null;
		$result = $this->library->archive(
			$this->_testPath . '/library_test_plugin',
			$this->_testPath . '/library_test_plugin'
		);
		$this->assertTrue($result);

		$expected = "library_test_plugin.phar.gz created in {$this->_testPath} from ";
		$expected .= "{$this->_testPath}/library_test_plugin\n";
		$result = $this->library->response->output;
		$this->assertEqual($expected, $result);

		Phar::unlinkArchive($this->_testPath . '/library_test_plugin.phar');
		Phar::unlinkArchive($this->_testPath . '/library_test_plugin.phar.gz');
		$this->_cleanUp();
	}

	public function testPushWithAuth() {
		$this->skipIf(!extension_loaded('zlib'), 'The zlib extension is not loaded.');
		$this->skipIf(
			ini_get('phar.readonly') == '1',
			'Skipped test {:class}::{:function}() - INI setting phar.readonly = On'
		);
		$result = $this->library->extract('plugin', $this->_testPath . '/library_test_plugin');
		$this->assertTrue($result);

		$result = file_put_contents(
			$this->_testPath . '/library_test_plugin/config/library_test_plugin.json',
			json_encode(array(
				'name' => 'library_test_plugin',
				'version' => '1.0',
				'summary' => 'something',
				'sources' => array(
					'phar' => 'http://somewhere.com/download/library_test_plugin.phar.gz'
				)
			))
		);
		$this->assertTrue($result);

		$result = $this->library->archive(
			$this->_testPath . '/library_test_plugin',
			$this->_testPath . '/library_test_plugin'
		);
		$this->assertTrue($result);

		$result = file_exists($this->_testPath . '/library_test_plugin.phar.gz');
		$this->assertTrue($result);

		$this->library->response->output = null;
		$this->library->username = 'gwoo';
		$this->library->password = 'password';
		$result = $this->library->push('library_test_plugin');
		$this->assertTrue($result);

		$expected = "library_test_plugin added to {$this->library->server}.\n";
		$expected .= "See http://{$this->library->server}/lab/plugins/view/{$result->id}\n";
		$result = $this->library->response->output;
		$this->assertEqual($expected, $result);

		$result = file_exists($this->_testPath . '/library_test_plugin');
		$this->assertTrue($result);

		$this->library->response->error = null;
		$this->library->response->output = null;
		$this->library->username = 'bob';
		$this->library->password = 'password';
		$result = $this->library->push('library_test_plugin');
		$this->assertFalse($result);

		$expected = "Invalid username/password.\n";
		$result = $this->library->response->error;
		$this->assertEqual($expected, $result);

		$result = file_exists($this->_testPath . '/library_test_plugin');
		$this->assertTrue($result);

		Phar::unlinkArchive($this->_testPath . '/library_test_plugin.phar');
		Phar::unlinkArchive($this->_testPath . '/library_test_plugin.phar.gz');
		$this->_cleanUp();
	}


	public function testPushNotValid() {
		$this->skipIf(!extension_loaded('zlib'), 'The zlib extension is not loaded.');
		$this->skipIf(
			ini_get('phar.readonly') == '1',
			'Skipped test {:class}::{:function}() - INI setting phar.readonly = On'
		);
		$this->library->library = 'library_plugin_test';
		$path = $this->_testPath;

		$expected = true;
		$result = $this->library->extract('plugin', "{$path}/library_test_plugin");
		$this->assertEqual($expected, $result);
		$this->library->response->output = null;

		$file = $this->_testPath . '/library_test_plugin/config/library_test_plugin.json';
		$result = file_put_contents(
			$file,
			json_encode(array(
				'name' => 'library_test_plugin',
				'version' => '1.0',
				'summary' => 'something',
			))
		);
		$this->assertTrue($result);

		$result = $this->library->archive(
			$this->_testPath . '/library_test_plugin',
			$this->_testPath . '/library_test_plugin'
		);
		$this->assertTrue($result);

		$expected = "library_test_plugin.phar.gz created in {$this->_testPath} from ";
		$expected .= "{$this->_testPath}/library_test_plugin\n";
		$result = $this->library->response->output;
		$this->assertEqual($expected, $result);

		$result = file_exists($this->_testPath . '/library_test_plugin.phar.gz');
		$this->assertTrue($result);
		$this->library->response->output = null;

		$result = $this->library->push('library_test_plugin');
		$this->assertFalse($result);

		$expected = "/The forumla for library_test_plugin is not valid/";
		$result = $this->library->response->error;
		$this->assertPattern($expected, $result);

		$result = is_dir($this->_testPath . '/library_test_plugin');
		$this->assertTrue($result);

		Phar::unlinkArchive($this->_testPath . '/library_test_plugin.phar');
		Phar::unlinkArchive($this->_testPath . '/library_test_plugin.phar.gz');
		$this->_cleanUp();
	}

	public function testNoArchive() {
		$result = $this->library->archive(
			$this->_testPath . '/library_test_plugin',
			$this->_testPath . '/library_test_plugin'
		);
		$this->assertFalse($result);

		$expected = "/Could not create archive from/";
		$result = $this->library->response->error;
		$this->assertPattern($expected, $result);
	}
}

?>