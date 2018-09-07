<?php

/**
 * Body parts location
 */
define( 'PARTS',	\dirname( __FILE__ ) . '/parts/' );

/**
 *  Minimum avatar size
 */
define( 'SIZE_MIN',	16 );

/**
 *  Maximum avatar size
 */
define( 'SIZE_MAX',	400 );

/**
 *  Seed hash algorithm
 */
define( 'ALGO',	'sha256' );

/**
 *  Suhosin aware checking for function availability
 *  
 *  @param string $func Function name
 *  @return boolean true If the function exists
 */
function missing( $func ) {
	static $exts;
	static $blocked;
	
	if ( \extension_loaded( 'suhosin' ) ) {
		if ( !isset( $exts ) ) {
			$exts = \ini_get( 'suhosin.executor.func.blacklist' );
		}
		if ( !empty( $exts ) ) {
			if ( !isset( $blocked ) ) {
				$blocked = \explode( ',', \strtolower( $exts ) );
				$blocked = \array_map( 'trim', $blocked );
			}
			
			$search = \strtolower( $func );
			
			return (
				false	== \function_exists( $func ) && 
				true	== \array_search( $search, $blocked ) 
			);
		}
	}
	
	return !\function_exists( $func );
}

function generate() {
	// Check for access to required functions
	$req	= [
		'imagecreatetruecolor',
		'imagecopyresampled',
		'imagecolorallocate',
		'imagecreatefrompng',
		'imageSaveAlpha',
		'imagedestroy',
		'imagecopy',
		'imagefill',
		'imagepng',
	];
	
	$miss	= [];
	foreach ( $req as $f => $name ) {
		if ( missing( $name ) ) {
			$miss[] = $name;
		}
	}
	
	if ( !empty( $miss ) ) {
		die( 
			'Following GD function(s) required: ' . 
			\implode( ', ', $miss ) 
		);
	}
	
	//  Request filter
	$params	= 
	\filter_input_array( \INPUT_GET, [
		'size'	=> [
			'filter'	=> \FILTER_VALIDATE_INT,
			'options'	=> [
				'min_range'	=> SIZE_MIN,
				'max_range'	=> SIZE_MAX,
				'default'	=> SIZE_MIN
			]
		],
		'seed'	=> [
			'filter'	=> \FILTER_SANITIZE_ENCODED
			'options'	=> [
				'default'	=> ''
			]
		]
	]);
	
	// Send to builder
	build_monster( $params['seed'], $params['size'] );
}

function build_monster( $seed, $size ){
	
	// init random seed
	if ( !empty( $seed ) ) {
		$h = \hexdec( \substr( \hash( ALGO, $seed ), 0, 6 ) );
		\mt_srand( $h );
	}

	// throw the dice for body parts
	$parts = array(
		'legs'		=> \mt_rand(1,5),
		'hair'		=> \mt_rand(1,5),
		'arms'		=> \mt_rand(1,5),
		'body'		=> \mt_rand(1,15),
		'eyes'		=> \mt_rand(1,15),
		'mouth'	=> \mt_rand(1,10)
	);

	// create backgound
	$monster	= \imagecreatetruecolor( 120, 120 );
	$white		= \imagecolorallocate( $monster, 255, 255, 255 );
	
	\imagefill( $monster,0,0,$white );

	// add parts
	foreach( $parts as $part => $num ){
		$file	= PARTS . $part. '_' . $num . '.png';
		$im	=  \imagecreatefrompng( $file );
		if( !$im ) {
			die( 'Failed to load ' . $file )
		};
		
		\imageSaveAlpha( $im, true );
		\imagecopy( $monster, $im, 0, 0, 0, 0, 120, 120 );
		\imagedestroy( $im );

		// color the body
		if( $part == 'body' ){
			$color	= 
			\imagecolorallocate( 
				$monster, 
				\mt_rand( 20, 235 ), 
				\mt_rand( 20, 235 ), 
				\mt_rand( 20,235 ) 
			);
			\imagefill( $monster, 60, 60, $color );
		}
	}

	// restore random seed
	if ( !empty( $seed ) ) {
		\mt_srand();
	}

	// resize if needed, then output
	if ( $size && $size < 400 ){
		$out = \imagecreatetruecolor( $size, $size );
		\imagecopyresampled(
			$out,
			$monster, 0, 0, 0, 0, 
			$size, $size, 120, 120 
		);
		
		\header( "Content-type: image/png" );
		\imagepng( $out );
		\imagedestroy( $out );
		\imagedestroy( $monster );
	} else {
		\header( "Content-type: image/png" );
		\imagepng( $monster );
		\imagedestroy( $monster );
	}
}

// Create MonsterID
generate();

