
<?php
    // Deputado Histogramado
	// RC1 - 7/Mar/2017

	// AJAX backend script - receives a word (=POST['word']) 
	// 			and counts occurences of this string in the portuguese parliament sessions
	//			returns array [{'date':'1976-10-20','counts':39},{'date':'1976-10-22','counts':30},...]
	//  		followed by the time it took to process the data in milliseconds. takes around 30 secs to load everything from disk (csv)
	//			 and put it into cache (1st run). Following/cached sessions take around 5 seconds 
	//			

	// cache mechanism: memcached. default chunk size is not adequate (1MB). I'm running it with the following settings:
	//  'memcached -m 768M -I 128M'

	// the better design would be to use some kind of persistent array in memory and avoid, reading/caching, but when I started the database was around 800mb and the server available for this only had 1GB of RAM, and is running other things. So there must be a plan for offloading the data. By preprocessing the data the DB size dropped to 550 MB which is much better, but the old code still stayed. The caching mechanism is much faster now with 550MB - probably was dumping some of the data to disk with 800 MB. Using a beefier server would solve all these issues. 


 //       ini_set('display_errors', 1);
//        error_reporting(E_ALL ^ E_NOTICE);

        $palavra = $_POST["word"];
        $palavra = strtolower($palavra);

        if(strlen($palavra)<2)
        {
            echo('[];0');
            return;
        }   

        $memcache = new Memcached();
        $memcache->addServer('127.0.0.1', 11211) or die('no memcached');

    
        $start  = round(microtime(true) * 1000);   

        echo('[');

        $N = 100;
        $i = 0;
        $data = Null;
        $key = 'k'.$i;
        $data = $memcache->get($key);
        if($data) 
        {
            for($i = 0; $i < 3924; $i+=$N)
            {
                $key = 'k'.$i;
                $data = $memcache->get($key);
                
                foreach($data as $d)
                {
                    $count = substr_count( $d[1], $palavra );
                    if($count > 0)
                    {
                        echo("{\"date\":\"");
                        echo($d[2]);
                        echo("\",\"count\":");
                        echo($count);
                        echo("},"); 
                    } 
                }
                
            }
            
        }else
        {
            $f = fopen("sessoes_democratica_clipped.csv", "r");
            $data = fgetcsv($f);
            
            
            
            for($i = 0; $i < 3924; $i+=$N)
            {
                $array = [];
                $r = 0;
                for($j = 0; $j < $N & $r !== FALSE; $j++)
                {
                    $r = fgetcsv($f);
                    if($r == FALSE) break;
                    $array[$j] = $r;
                }
                $key = 'k'.$i;
                $memcache->set($key, $array);// or die ("Failed to save data at the server");
                
                    $count = substr_count( $d[1], $palavra );
                    if($count > 0)
                    {
                        echo("{\"date\":\"");
                        echo($d[2]);
                        echo("\",\"count\":");
                        echo($count);
                        echo("},"); 
                    } 
                
            }
            
            fclose($f);
        }
        
 
        echo(']');
            
        $end  = round(microtime(true) * 1000);   
        echo(';');
        echo($end-$start); 
            


// OLD CODE OF MYSQL IMPLEMENTATION. WAS TOO SLOW (30-60 sec per query)

            /*        
    //    echo("<p>php start</p>");
        
        $link = mysqli_connect('127.0.0.1:8889', 'root', 'root')   
            or die('Could not connect: ' . mysqli_error());
    //    echo 'Connected successfully<br/>';
        mysqli_select_db($link, 'deputado') or die('Could not select database');


/*   
        $query = 'SELECT count(*) as total FROM tabela_sessoes2 ';
        $result = mysqli_query($link, $query) or die('Query failed: ' . mysqli_error());
        
        //$val = mysqli_fetch_array($result, MYSQLI_NUM);
        $data=mysqli_fetch_assoc($result);
        $nrows = $data['total'];
        echo('<p>num values:');
        echo($nrows);
        echo('</p>');
    
      


        $memcache = new Memcached();
        $memcache->addServer('127.0.0.1', 11211);

        //$memcache->set('key', $palavra);/// or die ("Failed to save data at the server");
        //echo($memcache->getResultMessage());
        //echo('<br/>');
            //or echo ("Could not connect to memcached");

        //$version = $memcache->getVersion();
        //echo('<br/>');
        //echo($version);
        //echo('<br/>');


//$memcache->set('key', $tmp_object, false, 10) or die ("Failed to save data at the server");
//echo "Store data in the cache (data will expire in 10 seconds)<br/>\n";

        //for($year = 1976; $year < 1980; $year++)
        $delta = 500;
        //$nrows = 1000;
        //for($offset=0; $offset < $nrows; $offset+=$delta)
        for($offset=0; $offset < 2000; $offset+=$delta)
        {
            echo('<h2>');
            echo($offset);
            echo('</h2>');
            //request sql all entries from 
            $end = $offset+$delta;
            if($end > $nrows)
                $end = $nrows;
            
            
            $data = Null;
            $key = 'k'.$offset;
            $data = $memcache->get($key);
            if($data)
            {
                echo('<p>cache</p>');
                $result = $data;
                $data = Null;
                $result0 = Null;
            }else
            {
                $query = sprintf('SELECT * FROM tabela_sessoes2 LIMIT %d,%d',$offset,$end);
                #$query = 'SELECT * FROM tabela_sessoes ';
                $result0 = mysqli_query($link, $query) or die('Query failed: ' . mysqli_error());
                $result = $result0->fetch_all();
                mysqli_free_result($result0);
                $memcache->set($key, $result);// or die ("Failed to save data at the server");
                //echo($memcache->getResultMessage());
                echo('<p>not cache</p>');
            }
            // Printing results in HTML
            
            $i = 0;
            //while ($line = mysqli_fetch_array($result, MYSQLI_NUM)) 
            //foreach ($result as $line) 
            {
                //echo($i++); echo(' '); echo($line[2]);
                //echo('<br>');
                
                /*$count = substr_count( $line[2], $palavra );
                if($count > 0)
                {
                    //echo("<h6>{\"");
                    echo("{\"date\":\"");
                    echo($line[1]);
                    echo("\",\"count\":");
                    echo($count);
                    echo("},"); 
                    //echo('</h6>');
                }   */
   /*         }
            
        // Free resultset
            
            
            $result = Null;
            echo(memory_get_usage());
            echo('<br>');
            gc_collect_cycles();
            echo(memory_get_usage());
        }*/

    // Closing connection
    //    mysqli_close($link);
    
       

    ?>