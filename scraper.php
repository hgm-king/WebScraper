<?php

//This was written by HG King on sept/22/2017


//praying this will fix the problem of my script timing out.
//stack overflow said it would help lol
//https://stackoverflow.com/questions/365496/how-to-keep-a-php-script-from-timing-out-because-of-a-long-mysql-query
//set_time_limit(0);
//ignore_user_abort(1);


//this function here will return the value between the end of $find and beginning of $to
//contained in $string
function stringFind_To($string, $find, $to)  {
	$pos = strpos($string, $find);
	if ($pos === false) {
		return NULL;
	}
	else {
		//$val is the position of the end of the search string $find
		$val = $pos + strlen($find);

		if($to != NULL)  {
			//find position of $to in $string starting at pos of $val
			$result = strpos($string, $to, $val);

			$distance = $result - $val;
			//grab string from $string, starting at $val and traverses to $result
			$answer = substr($string, $val, $distance);
		}
		//else there was no $to, read to end of string
		else  {
			$answer = substr($string, $val);
		}
		return $answer;
	}
}

function whoIs($url)  {

	//find out which TLD our url is (.com or .org)
	$ext = stringFind_To($url, ".", NULL);

	//this would be where you would add any other TLD
	// https://www.iana.org/domains/root/db has info on what WHOIS server to use
	if($ext == "com" || $ext == "net" || $ext == "edu")  {
		$server = "whois.verisign-grs.com";
	}
	else if($ext == "org")  {
		$server = "whois.pir.org";
	}
	else  {
		return NULL;
	}

	// Open a Socket connection to our WHOIS server
	$fp = fsockopen($server, 43);

	$out = $url . "\r\n";

	// Send the data
	fwrite($fp, $out);
	$whois = "";

	while (!feof($fp))  {
		$whois .= fgets($fp, 128);
		$whois .= '<br>';
	}

	// Close the Socket Connection
	fclose($fp);

	return $whois;
}

function doSearch($url, $mysqli)  {
	//if nothing works then theres still a value for these
	$wp = $theme = $version = $email = NULL;
	try  {
		//take url and put entire file into a string
		//borrowed this bit from stack overflow
		//https://stackoverflow.com/questions/10524748/why-im-getting-500-error-when-using-file-get-contents-but-works-in-a-browser
		$opts = array('http' => array('header' => "User-Agent:MyAgent/1.0\r\n"));
		$context = stream_context_create($opts);
		$string = file_get_contents($url, FALSE, $context);

		if($string === false)  {
			echo "Didnt work!";
			return false;
		}
		else  {
			//could be repetative since next search will contain 'wp-'
			$wordpress = stringFind_To($string, 'wp-', "/");
			if($wordpress != NULL)  {
				$wp = 1;
			}
			else $wp = 0;

			//first look for the theme
			$theme = stringFind_To($string, 'wp-content/themes/', "/");

			//find the version
			$version = stringFind_To($string, "meta name=\"generator\" content=\"", "\"");

			//now we do the whoIs lookup
			//change url into just domain name i.e. no http or www
			$dName = stringFind_To($url, "http://www.", NULL);
			if($dName != NULL)  {
				$whois = whoIs($dName);
			}
			else  {
				$whois = whoIs(stringFind_To($url, "http://", NULL));
			}

			if($whois != NULL)  {
				//search whois for the email here
				$email = stringFind_To($whois, "Admin Email: ", "<br>");
			}

			//sennnd itttt
			$sql = "INSERT INTO `WebScraper` (`URL`, `WordPress`, `Theme_Name`, `WordPress_Version`, `WhoIs_Email`, `Status`)";
			$sql .= " VALUES ('" . $url . "', '" . $wp . "', '" . $theme . "', '" . $version . "', '" . $email . "', 'Not Contacted');";
			$result = $mysqli->query($sql);
			return true;
		}
	}
	catch(Exception $e)  {
		//idk why but this will never run
		//I believe it is because I get a Warning rather than an Error
		echo "Yo this won't work b!<br>";
	}
}

function prepCSV($mysqli)  {
	//get files from directory, exclude . .. and .DS_STORE since those are wack
	$dir    = 'csv';
	$files  = array_slice(scandir($dir), 3);
	$flag = 0;

	qforeach($files as $file)  {
	  //if last file was done, reset flag
	  if($flag == 2)  {
	    $flag = 0;
	  }

	  echo $file . "<br>";

	  if($flag == 0)  {
	    echo $file . " is being started<br>";

	    $query = "SELECT * FROM `CSV` WHERE `Filename` = '" . $file . "';";

	    if ($result = $mysqli->query($query)) {
	      //if there is no db entry for this file
	      if($result->num_rows == 0)  {
	        //create a new one
	        $string = "INSERT INTO `CSV` (`Finished`, `Total`, `Filename`)";
	        $string .= "VALUES ('0', '0', '" . $file . "');";
	        echo $string . "<br>";

	        if ($mysqli->query($string) === TRUE) {
	          printf("successfully created.\n");
	        }

	        $inc = readCSV(0, $file, $mysqli);
	        $count = 0;
	        $flag = 1;
	      }
	      //else there is a db entry for this file
	      else  {
	        //should only run once
	        while($row = $result->fetch_assoc()) {
	          $status = $row["Finished"];
	          $count = $row["Total"];
	          echo "Count for this file is " . $count . "<br>";
	          //if it isnt already done
	          if($status == 0)  {
	            echo "It's not done yet!<br>";
	            $inc = readCSV($count, $file, $mysqli);
	            $flag = 1;
	          }
	          else  {
	            $flag = 2;
	          }
	        }
	      }
				//update the db entry
	      if($flag == 1)  {
	        if($inc == -1)  {
	          $string = "UPDATE `CSV` SET `Finished`='1'";
	        }
	        else  {
	          $string = "UPDATE `CSV` SET `Total`='" . ($inc) . "'";
	        }
					$string .= " WHERE `Filename`='". $file ."';";
	        if ($mysqli->query($string) === TRUE) {
	          printf("successfully updated.\n");
	        }
	      }

	      /* free result set */
	      $result->close();
	    }
	    else echo "There was an error making the query!";
	  }
	}
}

function readCSV($count, $file, $mysqli)  {
  $num = 50;
  $file = "csv/" . $file;
	//open that file
	if (($handle = fopen($file, "r")) !== FALSE) {
    $i = 0;
    $j = 0;
		//if theres stuff in the file still to be read
		while(($data = fgetcsv($handle, 1000, ",")) !== FALSE && ($i < $count + $num)) {
			//if its a good domain name to search
			if(isset($data[1]) && $data[1] != 'domain_name' && ($i > $count))  {
        //do the search and add the www. if necessary
        if(!(doSearch('http://' . $data[1], $mysqli)))  {
          doSearch('http://www.' . $data[1], $mysqli);
        }
        $j++;
      }
      $i++;
    }
    fclose($handle);
  }

  if($j == 0)  {
    return -1;
  }
  return $count + $num;

}


//
//begin here
//


echo "Hello world!";


$mysql_host     = "localhost";
$mysql_database = "WebScraper";
$mysql_user     = "root";
$mysql_password = "";


$mysqli = new mysqli($mysql_host, $mysql_user, $mysql_password, $mysql_database);

if($mysqli->connect_errno)  {
	echo "Couldn't connect to the database :( ";
	exit;
}

prepCSV($mysqli);




//Here are some individual sites that are definitely WP users

//doSearch('https://www.sonymusic.com', $mysqli);
//doSearch('https://techcrunch.com/', $mysqli);
//doSearch('https://www.katyperry.com/', $mysqli);
//doSearch('http://www.obama.org/', $mysqli);



//makeQuery("select * from `WebScraper` WHERE Wordpress = 1;", $mysqli);
//makeQuery("select * from `WebScraper`;", $mysqli);
mysqli_close($mysqli);


function makeQuery($sql, $mysqli)  {

	$result = $mysqli->query($sql);

	echo "<table><tr>";

	while($fieldInfo = mysqli_fetch_field($result))  {
		echo "<th>" . $fieldInfo->name . "</th>";
	}
	echo "</tr>";

	while($row = $result->fetch_array(MYSQLI_NUM))  {
		echo "<tr>";
		foreach($row as $value)  {
			echo "<td>" . $value . "</td>";
		}
		echo "</tr>";
	}
	echo "</table>";
}
?>
