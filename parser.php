<?php
header("Content-type: text/html; charset=utf-8");
require_once 'SHD/simple_html_dom.php';

// Database connection
$db_host = "localhost";
$db_name = "drive2ru";
$db_user = 'root';
$db_pass = "mysql1";
try {
    $opt = [
		PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		PDO::ATTR_EMULATE_PREPARES   => false,
	];
	$dbh = new PDO('mysql:host='.$db_host.';dbname='.$db_name.';charset=utf8', $db_user, $db_pass, $opt);
} catch (PDOException $e) {
	print "Error!: " . $e->getMessage() . "<br/>";
	die();
}

$options = getopt("n:l:");
$mrkName = $options['n'];
$mrkLink = $options['l'];

class Parser {
	protected $dbh;
	protected $baseurl;

    public function __construct($dbh, $baseurl) {
        $this->dbh = $dbh;
        $this->baseurl = $baseurl;
    }

    public function saveModel($name, $link, $mark) {
        $input = $this->dbh->prepare("INSERT INTO models (name, link, mark_id) VALUES (:name, :link, :mark_id)");
        $result = $input->execute(["name" => $name, "link" => $link, "mark_id" => $mark]);
        if ($result) {
        	return $this->dbh->lastInsertId();
        }
        return false;
    }

    public function saveGeneration($name, $link, $model) {
        $input = $this->dbh->prepare("INSERT INTO generations (name, link, model_id) VALUES (:name, :link, :model_id)");
        $result = $input->execute(["name" => $name, "link" => $link, "model_id" => $model]);
        if ($result) {
        	return $this->dbh->lastInsertId();
        }
        return false;
    }

    public function loadNewPage($url, $top = 0, $parent, $secondParent = null) {
    	$html = file_get_html($url);

		$models = $html->find('span.c-makes__item');
		$generations = $html->find('div.c-gen-card__caption');
		$journals = $html->find('div.c-car-card__caption');

		// process models list is exist
		if (count($models)) {
			foreach ($models as $model) { 
				$carModelName = trim($model->plaintext);
				$carModelLink = $this->baseurl.trim($model->children[0]->attr["href"]);
				$row = $this->saveModel($carModelName, $carModelLink, $parent);
				if ($row) $this->loadNewPage($carModelLink, 0, $row);
			}
		} else if ($top) { // if mark have only one model - copy mark as model
			$new = $this->dbh->prepare("SELECT name, link FROM marks WHERE id = :id");
			$new->execute(["id" => $parent]);
			$model = $new->fetch();
			$row = $this->saveModel($model['name'], $model['link'], $parent);
			//if ($row) $this->loadNewPage($model['link'], 0, $row);
		}

		// process generation list if exist
		if (count($generations)) {
			foreach ($generations as $gen) { 
				$carModelGenName = trim($gen->children[0]->plaintext);
				$carModelGenLink = $this->baseurl.trim($gen->children[0]->attr["href"]);
				$row = $this->saveGeneration($carModelGenName, $carModelGenLink, $parent);
				if ($row) $this->loadNewPage($carModelGenLink, 0, $parent, $row);
			}
		}

		if (count($journals)) {
			foreach ($journals as $journal) { 
				$journalName = trim($journal->children[0]->plaintext);
				$journalLink = $this->baseurl.trim($journal->children[0]->attr["href"]);-
				$this->loadJournal($journalName, $journalLink, $parent, $secondParent);
			}
		}

		$html->clear(); 
		unset($html);
    }

    public function loadJournal($name, $url, $model, $gen = null) {
    	$html = file_get_html($url);

		$images = $html->find('img.js-pichd');
		$title = $html->find('h1.c-car-info__caption')[0]->plaintext;
		$descr = $html->find('div.c-car-desc__text')[0]->innertext;
		$username = $html->find('div.c-car-userinfo')[0]->find('a.c-username')[0]->plaintext;
		$posts = $html->find('div.c-lb-card__body');

		$journal = $this->saveJournal(trim($title), $url, trim($username), $descr, $model, $gen);

		if ($journal) {
			if (count($images)) {
				foreach ($images as $image) {
					$src = $image->src;
					$imgName = end(explode("/", $src));
					file_put_contents('img/'.$imgName, file_get_contents($src));
					$this->saveImage($imgName, $journal);
				}
			}
			if (count($posts)) {
				foreach ($posts as $post) {
					$postTitle = trim($post->find('div.c-lb-card__title')[0]->plaintext);
					$postLink = $this->baseurl.trim($post->find('div.c-lb-card__title')[0]->children[0]->attr["href"]);
					$postCategory = trim($post->find('div.c-lb-card__meta')[0]->find('a.c-link')[0]->plaintext);
					$this->loadPost($postTitle, $postLink, $postCategory, $journal);
				}
			}
		}

		$html->clear(); 
		unset($html);
    }

    public function saveJournal($title, $link, $user, $desc, $model, $gen = null) {
        $input = $this->dbh->prepare("INSERT INTO journals (title, link, user, description, model_id, generation_id) VALUES (:title, :link, :user, :description, :model_id, :generation_id)");
        $result = $input->execute(["title" => $title, "link" => $link, "user" => $user, "description" => $desc, "model_id" => $model, "generation_id" => $gen]);
        if ($result) {
        	return $this->dbh->lastInsertId();
        }
        return false;
    }

    public function saveImage($imgName, $journal, $post = null) {
        $input = $this->dbh->prepare("INSERT INTO images (name, journal_id, post_id) VALUES (:name, :journal_id, :post_id)");
        $result = $input->execute(["name" => $imgName, "journal_id" => $journal, "post_id" => $post]);
        if ($result) {
        	return true;
        }
        return false;
    }

    public function savePost($title, $text, $category, $journal) {
        $input = $this->dbh->prepare("INSERT INTO posts (title, body_text, category, journal_id) VALUES (:title, :body_text, :category, :journal_id)");
        $result = $input->execute(["title" => $title, "body_text" => $text, "category" => $category, "journal_id" => $journal]);
        if ($result) {
        	return true;
        }
        return false;
    }

    public function loadPost($title, $url, $category, $journal) {
    	$html = file_get_html($url);

		$images = $html->find('div.c-post__pic');
		$text = $html->find('div.js-translate-text')[0]->plaintext;

		$post = $this->savePost($title, $text, $category, $journal);

		if ($post) {
			if (count($images)) {
				foreach ($images as $image) {
					$src = $image->find('img')[0]->src;
					$imgName = end(explode("/", $src));
					file_put_contents('img/'.$imgName, file_get_contents($src));
					$this->saveImage($imgName, $journal, $post);
				}
			}
		}

		$html->clear(); 
		unset($html);
    }
}

class Mark {
	protected $name;
	protected $link;
	protected $dbh;
	protected $parser;

	public function __construct($dbh, $parser, $name, $link) {
		$this->dbh = $dbh;
		$this->parser = $parser;
		$this->name = $name;
		$this->link = $link;
	}

  	public function run() {
	    $input = $this->dbh->prepare("INSERT INTO marks (name, link) VALUES (:name, :link)");
        $result = $input->execute(["name" => $this->name, "link" => $this->link]);
        if ($result) {
        	$id = $this->dbh->lastInsertId();
        	$this->parser->loadNewPage($this->link, 1, $id);
        }
  	}
}

// parse marks list
$baseurl = 'https://www.drive2.ru';

// run
$parser = new Parser($dbh, $baseurl);
$thisMark = new Mark($dbh, $parser, $mrkName, $mrkLink);
$thisMark->run();

?>