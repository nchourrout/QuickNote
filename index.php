<?php
/*
 * QuickNote (0.1)
 * by Nicolas Chourrout (http://nchourrout.fr)
 * nchourrout [at] gmail [dot] com
 *
 *
 * Copyright (c) 2010 Nicolas Chourrout (http://nchourrout.fr)
 * Licensed under the GPL (GPL-LICENSE.txt) license. 
 *
 */

$configFile = "settings.conf"; 
$backupDir = "";

//Looking for configFile
if ( file_exists( $configFile ) )
{
	$bLogged = checkAuth( $configFile );
	$ini_array = parse_ini_file( $configFile );
	$defaultNote = $ini_array[ 'default_note' ];
	$filePath = $ini_array['note_folder'];
	if ( isset( $ini_array[ 'backup_dir' ] ) )
		$backupDir = $ini_array[ 'backup_dir' ];
}
else //Deal without it
{
	$filePath = "files/";
	$bLogged = true;
	$defaultNote = "default";
}

$note = $defaultNote;
$filename = $filePath . $defaultNote . ".txt";
$bEnd = false;
$editor_visible = false;


if ( isset( $_POST[ 'note' ] ) ) 
{	
	$params = parseArgs( stripslashes( $_POST[ 'note' ] ) );
	if( count( $params ) > 0 )
	{		
		foreach( $params as $key => $value )
		{
			switch( (string)$key )
			{		
				case '0': //A note name was specified
					if ( $bLogged && $value != "" )
					{
						$arrayNote = setNote( $value , $filePath );
						if( count( $arrayNote ) == 0 )
						{
							$page_content .= '<p class="error">No note matching ' . $value . '</p>';
							$bEnd = true;
							$note = "";
							break 2;
						}
						elseif( count ( $arrayNote ) > 1 )
						{
							$page_content .= '<p>Notes matching ' . $value . '</p>';
							$page_content .= '<ul>';
							foreach( $arrayNote as $f )
								$page_content .= '<li>' . $f . '</li>';
							$page_content .= '</ul>';
							$bEnd = true;
							$note= "";
							break 2;
						}else{
							$note = $arrayNote[ 0 ];
							$filename = $filePath . $note . ".txt";
						}
					}
					break;
					
				case 'a':
					if ( $bLogged && $value != "") 
						add( $filename , $value );
					break;
					
				case 'd':
					if ( $bLogged && $value != "" )
					{
						$page_content .= deleteLine( $filename , $value );
					}
					break;
				
				case 'o':	
					if ( $bLogged && $value != "" )
					{
						$position = $value;
						if ( $contentInsert )
							$page_content .= insertLine($filename , $position , $contentInsert );
					}
					break;
					
				case 'i':
					if ( $bLogged && $value != "")
					{
						$contentInsert = $value;
						if ( $position )
							 $page_content .= insertLine( $filename , $position , $contentInsert );
					}
					break;
					
				case 'z':
					if ( $bLogged )
					{
						$page_content .= archiveInfo( $backupDir );
						$bEnd = true;
						$note="";
					}
					break;
					
				case 'x':
					if ( $bLogged )
					{
						$page_content .= delete( $filename );
						$bEnd = true;
					}
					break;
					
				case 'e':
					if ( $bLogged )
					{
						$editor_text .= editor( $filename );
						$editor_visible = true;
						$bEnd = true;
					}
					break 2;
				
				case 'l':
		 			if ( $bLogged )
					{
					 	$page_content .= listNotes( $filePath );
						$bEnd = true;
						$note="";
					}	
					break;
					
				case 's':
					if ( $bLogged  )
					{
						if ( setDefaultNote( $configFile , $note ) )
							$page_content .= '<p class="notif">Default note set to ' . $note . '</p>';
					}
					break;
					
				case 'h':
					$page_content .= help();
					$bEnd = true;
					$note="";
					break;
					
				case 'u':
					$user = $value;
					if ( $password )
						$bLogged = connect( $configFile , $user , $password );
					if ( $bLogged ) {
						$bEnd = true;
						$page_content .= '<p class="notif">Logged in</p>';
						$note="";
					}
					break;
					
				case 'p' :
					$password = $value;
					if ( $user )
						$bLogged = connect( $configFile , $user , $password );
					if ( $bLogged ){
						$bEnd = true;
						$page_content .= '<p class="notif">Logged in</p>';
						$note="";
					}
					break;

				case 'q':
					$page_content = logout();
					$bEnd = true;
					$note="";
					break;
				
				case 'about':
					$page_content .= about();
					$bEnd = true;
					$note="";
					break;
				
				default : 
					$page_content .= '<p class="error">Syntax error : try --h for help</p>';
					$bEnd = true;
					$note="";
					break 2;
			}
		}	
	}
	
	if ( !$bEnd && $bLogged )
	{
		if ( !file_exists( $filename ) )
			add( $filename );
		$page_content .= read( $filename );
	} 

}
elseif( $bLogged && isset( $_POST[ 'editor_content' ] ) && !empty( $_POST[ 'editor_content' ] ) 
		&& isset( $_POST[ 'editor_note' ] ) && !empty( $_POST[ 'editor_note' ] )  )
{
	$note = ( $_POST[ 'editor_note' ] );
	$filename = $filePath . $note . ".txt";
	$editor_text .= editor( $filename , stripslashes( $_POST[ 'editor_content' ] ) );
	$editor_visible = true;
}
else
	$note = "";

$page_title = ( $note!="" ) ? "QuickNote - " . $note : "QuickNote";

if ( !$bLogged && !$page_content )
{
	$page_content .= '<p class="error">You must be logged in</p>'; 
}

function editor( $filename , $content = null ){
	if ( $content != null || !file_exists( $filename ) )
	{
		$editor_text = $content;
		file_put_contents( $filename , $content );
	}
	else
	{
		$editor_text = file_get_contents( $filename );
	}
	return $editor_text;
}

function setNote( $note , $filePath )
{		
	if ( preg_match( "[\*]" , $note ) )
	{
		$res = array();
		$arr = browse( $filePath );
		foreach ( $arr as $f ){
			if( preg_match( "/(^" . str_replace( "*" , "(.*)" , $note ) . ".txt)/" , $f ) == 1 )
			{
				$res[] = str_replace( ".txt" , "" , $f );
			}
		}
	}else
		$res = array( $note );
	return $res;
}

function checkAuth( $configFile )
{
	$ini_array = parse_ini_file( $configFile );
	$login = $ini_array[ 'login' ];
	$pass = $ini_array[ 'pass' ];
	
	if ( isset ( $_COOKIE[ 'login' ] ) && isset( $_COOKIE[ 'pass' ] ) )
		if( $_COOKIE[ 'login' ]  == md5( $login ) && $_COOKIE[ 'pass' ]  == md5( $pass ) )
			return true;		  
			
	return false;
}

function connect( $configFile ,  $loginEntered , $passEntered )
{
	$ini_array = parse_ini_file( $configFile );
	$login = $ini_array[ 'login' ];
	$pass = $ini_array[ 'pass' ];
	
	if ( $login == $loginEntered && $pass == $passEntered )
	{
		setcookie( 'login' , md5( $login ) , time() + 60 * 60 * 24 * 365 );
		setcookie( 'pass' , md5( $pass ) , time() + 60 * 60 * 24 * 365 );
		return true;
	}
	return false;
}

function logout()
{
		setcookie('login', "" , time() - 3600 );
		setcookie('pass', "" , time() - 3600 );
		return '<p class="notif">Logged out</p>';
}

function add( $filename , $content="")
{
	$content = ($content!="") ? $content."\n" : $content;
	return file_put_contents( $filename , $content , FILE_APPEND );
}

function read( $filename )
{
	$file = file( $filename );
	foreach ( $file as $key => $line )
		$table .= '<tr><td class="line_number">' . ( $key + 1 ) . '</td><td>' .  linkify( $line ). "</td></tr>";
	$table = '<table>' . $table . '</table>';
	return $table;
}

function linkify( $text )
{
  return preg_replace('@((http?://)?(([-\w]+\.[-\w\.]+)+\w(:\d+)?(/([-\w/_\.]*(\?\S+)?)?)*))@', '<a href="http://$3">$1</a>' , $text);
}

function deleteLine( $filename , $delLine )
{
	if( !file_exists( $filename ) )
		return '<p class="error>The file does not exist</p>"';
		
	$array = file( $filename );
	
	if( $delLine > count( $array ) )
		return '<p class="error>No line ' . $delline . '</p>"';
		
	$delLine = ( $delLine < 0 ) ? count( $array ) + $delLine + 1 : $delLine; //Allow negative indexes
	
	unset( $array[ $delLine - 1 ] );
	$content = implode( "" , $array );
	file_put_contents( $filename , $content ) ;
}

function insertLine( $filename, $position, $content )
{
	if( !file_exists( $filename) )
		return '<p class="error>The file does not exist</p>';
	$array = file( $filename );
	
	if( $position > count( $array ) ) $position = count( $array );
	
	$position = ( $position < 0 ) ? count( $array ) + $position + 1 : $position; //Allow negative indexes
	
	$before = array_slice( $array , 0, $position );
	$after = array_slice( $array , $position );
	file_put_contents( $filename , implode( "" , $before ) . $content . "\n" . implode( "" , $after ) ) ;
}

function delete( $filename )
{

	if ( file_exists( $filename ) )
	{
		unlink( $filename );
		return '<p class="notif">File deleted successfully</p>';
	}
	else
	{
		return '<p class="error">File doesn\'t exist</p>';
	}
}

function archiveInfo( $backupDir )
{
	if ( $backupDir != "" )
	{
		$content = "<p>Archives</p>";
		$array1 = browse( $backupDir );
		$content.="<ul>";
		foreach( $array1 as $file )
			$content.='<li><a href="' . $backupDir . $file.'">'.$file.'</a></li>';
		$content.="</ul>";
	}else
		$content="";

	return $content;
	
}

function listNotes( $dir )
{
	$array = browse( $dir , array( "simplenotesync.db" ) );
	$content = "<p>Notes</p>";
	if ( count( $array ) > 0 )
	{
		$content .= "<ul>";
		foreach( $array as $file )
			$content .= '<li>'.substr( $file , 0 , -4).'</li>';
		$content .= "</ul>";
	}
	else
		$content = '<p class="notif" >No notes</p>';
	return $content;
}

function browse( $dir, $arrIgnore = Array() )
{
	$dir_handle = @opendir( $dir ) or die('<p class="error">Cannot open folder : ' . $dir . '</p>');
	$res = Array();
	while( false !== ( $file = readdir( $dir_handle ) ) )
	{
		if($file == "." || $file == ".." || in_array( $file , $arrIgnore ) ) continue;
		$res[] =  $file;
	}
	sort( $res );
	return $res;
}

function setDefaultNote( $configFile , $note  )
{
	$ini_array = parse_ini_file( $configFile );
	if ( $ini_array )
	{
		$ini_array[ "default_note" ] = $note;
		return write_ini_file( $ini_array , $configFile );
		
	}else
		return false;
}

function write_ini_file($assoc_arr, $path, $has_sections=FALSE) 
{
    $content = "";

    if ( $has_sections ) {
        foreach ( $assoc_arr as $key => $elem ) {
            $content .= "[".$key."]\n";
            foreach ( $elem as $key2 => $elem2 )
            {
                if( is_array( $elem2 ) )
                {
                    for( $i=0 ; $i < count( $elem2 ) ; $i++ )
                    {
                        $content .= $key2 . "[] = \"" . $elem2[$i] . "\"\n";
                    }
                }
                else if( $elem2 == "" ) $content .= $key2." = \n";
                else $content .= $key2 . " = \"" . $elem2 . "\"\n";
            }
        }
    }
    else
    {
        foreach ( $assoc_arr as $key => $elem )
        {
            if( is_array( $elem ) )
            {
                for( $i = 0 ; $i < count( $elem ) ; $i++ )
                {
                    $content .= $key . "[] = \"" . $elem[ $i ]."\"\n";
                }
            }
            else if( $elem == "" ) $content .= $key." = \n";
            else $content .= $key . " = \"" . $elem . "\"\n";
        }
    }

    if ( !$handle = fopen( $path , 'w' ) ) {
        return false;
    }
    if ( !fwrite( $handle , $content ) ) {
        return false;
    }
    fclose( $handle );
    return true;
}

function help()
{
	return 	'<pre>
Syntax : n [NOTE] [ACTIONS]

	n NOTE 
		Read a note if it exists, create it otherwise (wildcards are supported)
		
	if NOTE is not specified a default note is used
	
	Actions :
	
		--a CONTENT
			Add CONTENT to a note, create the note if it doesn\'t exist
	
		--d LINE
			Delete line LINE (negative indexes allowed)
		
		--i CONTENT --o LINEOFFSET
			Insert CONTENT in a note after line LINEOFFSET (negative indexes allowed)
		
		--l
			List all notes
				
		--x
			Delete note
			
		--e
			Open the note in a simple text editor (Features : autosave and autoresize)
		
		--h
			Display this help message
			
		--about
			Version information and links to documentation
	
	Optional :
	
		Password protection : activated if settings.conf exists
		
		--u USER --p PASSWORD
			Connect as USER with PASSWORD
	
		--q
			Logout
			
		Hourly backup : requires crontab scheduled task and backup_dir set in settings.conf
		
		--z
			List archives
			
		Modify default note using command line : activated if settings.conf exists
			
		--s 
			Set new default note as NOTE

		Synchronization with Simplenote with Simplenotesync.pl
			http://fletcherpenney.net/other_projects/simplenotesync/
		
	Content of settings.conf :
	
		login = LOGIN
		pass = PASSWORD
		default_note = DEFAULTNOTE
		backup_dir = BACKUPDIR
		note_folder = NOTEFOLDER
		
	Examples :
		
		n
			Display default note
		
		n Presents
			Display note "Presents"
			
		n --u root --p mypwd
			Login as root
			
		n --l
			Display notes list
		
		n todo --s
			Set default note to todo
		
		n --a Buy Milk
			Add "Buy Milk" to todo note
				
		n --d -1 
			Remove last added item to todo note
			
		n Things I want to do when I\'m 50 years old --a Live in Patagonia
			Create a note named "Things I want to do when I\'m 50 years old" with content "Live in Patagonia"
			If this note already exists, the content is appended at the end
		
		n *50* --e
			Edit previous note in a text editor
			
		n --q
			Logout
			
	</pre>';	
}

function about()
{
	return '<p>QuickNote is a simple note editing software you can control from within your browser address bar.
	<br/><br/><a href="http://wiki.github.com/nchourrout/QuickNote/">Documentation</a>
	<br/><br/>Current version : v0.1<br/><br/>
	by <a href="http://nchourrout.fr">Nicolas Chourrout</a></p>';
}

function parseArgs( $cmdLine )
{
	
	$res = array();	
	if( $cmdLine != "" )
	{
		$args = explode( " " , $cmdLine );
		for( $i = 0 ; $i < count( $args ) ; $i++ )
		{
			$str = $args[ $i ];
			if ( strlen( $str ) > 2 && substr( $str , 0 , 2 ) == '--' )
			{	
				$key = substr( $str , 2 );
				
				if( isset( $args[ $i + 1 ] ) && substr( $args[ $i + 1 ] , 0 , 2) != "--" )
				{
					$res[ $key ] = $args[ $i + 1 ];
					$i++;
				}
				else
				{
					$res[ $key ] = "";
				}
			}
			else
			{
				$last_index = array_keys( $res );
				if( $last_index ) 
				{
					$last_index = $last_index[ count( $last_index ) - 1  ];
					$res[ $last_index ] .= " " .$str;
				}
				else
				{
					$res[ ] = $str;
				}
			}
		}
	}
	return $res;
}

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
		<link rel="stylesheet" title="screen" href="style.css" type="text/css"/>
		<script type="text/javascript" src="http://code.jquery.com/jquery-1.4.2.min.js"></script>
		<script type="text/javascript" src="jquery.simpleautogrow.js"></script>
		<title><?php echo $page_title ?></title>
	</head>
	<body onload="document.getElementById('note').focus()">
		<script type="text/javascript">
			$(document).ready(function(){
				$('textarea').simpleautogrow();
			});
			
			function triggerAutosave()
			{
				$('#submit_editor').removeAttr('disabled');
				$("#submit_editor").attr('value', 'Save');
				var t = setTimeout("autosave()", 5000);
			}
			
			function autosave()
			{
				var content = $("#editor_content").val();
				var note = $("#editor_note").val();
							
				if ( content.length > 0 )
				{
					$.ajax(
					{
						type: "POST",
						url: "index.php",
						data: "editor_content=" + content + "&editor_note=" + note ,
						cache: false,
						success: function(message)
						{	
							$("#submit_editor").attr('disabled', 'disabled');
							$("#submit_editor").attr('value', 'Saved');
						}
					});
				}
			} 
		</script>
		<!-- Help Tip -->
		<p id="help_tip">--h for help</p>
		<!-- Container -->
		<div id="container">
			<!-- Title -->
			<h1><?php echo $page_title ?></h1>
			
			<!-- Content : -->
			<?php echo $page_content ?>	
			
			<!-- Editor -->
			<?php $editor_style = ( $editor_visible )? "display:block" : "display:none"; ?>
			<form name="editor" id="editor" method="post" style="<?php echo $editor_style ?>" action="">
					<textarea rows="1" cols="100" id="editor_content" name="editor_content" onkeypress="triggerAutosave()"><?php echo $editor_text ?></textarea>
					<input type="hidden" name="editor_note" id="editor_note" value="<?php echo $note ?>" autocomplete="off"/>
					<br/><br/><input type="submit" value="Saved" id="submit_editor" disabled="disabled"/>
					<br/><br/>
			</form>
			
			<!-- Command Line Input -->
			<form method="post" id="form" action="">
				<input type="text" id="note" name="note" />
			</form>
		</div>
	</body>
</html>
 
