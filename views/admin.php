<h2><?= esc_html( get_admin_page_title() ) ?></h2>
<p>Log of sent messages </p>
<?php
	// prerequisite
	if( empty( $results ) ){
		echo 'No results available';
	}
	echo "<p align='right'><a href='./?page=sendmail-report&download=csv'>Download CSV</a></p>";
	echo '<table border="2" width="100%">';
	// labels
	if( array_key_exists(0, $results) ){
		$keys = array_keys((array)$results[0]);
		echo "<tr>";
		foreach( $keys as $key ){
			echo "<td><b>". ucwords( str_replace("_", " ", $key) ) ."</b></td>";
		}
		echo "</tr>";
	}
	foreach( $results as $entry ){
		echo "<tr>";
		foreach( $entry as $field ){
			echo "<td>". $field ."</td>";
		}
		echo "</tr>";
	}
	echo '</table>';
	// pagination
	if( !empty( $pages ) ){
	echo '<div class="pagination">';
	echo 'Pages' .': ';
	for( $i=1; $i <= $pages; $i++ ){
		echo ($i == $page) // assume $page at this point...
			? $i
			: '<a href="?page=sendmail-report&results='. $i .'">'. $i .'</a> ';
	}
	echo '</div>';
	}
?>
