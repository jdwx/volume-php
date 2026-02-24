<?php


declare( strict_types = 1 );


namespace JDWX\Volume;


use JDWX\Strict\OK;


final class Helper {


    public static function containsWildcard( string $i_stPath ) : bool {
        return str_contains( $i_stPath, '*' )
            || str_contains( $i_stPath, '?' )
            || str_contains( $i_stPath, '[' );
    }


    public static function existingIsError( string $i_stPath ) : string|Error {
        if ( ! file_exists( $i_stPath ) ) {
            return $i_stPath;
        }
        if ( is_link( $i_stPath ) ) {
            return Error::PATH_IS_WEIRD;
        }
        if ( is_dir( $i_stPath ) ) {
            return Error::PATH_IS_DIRECTORY;
        }
        if ( is_file( $i_stPath ) ) {
            return Error::PATH_IS_FILE;
        }
        return Error::PATH_IS_WEIRD;
    }


    public static function fileFromPath( string $i_stPath ) : string {
        while ( str_ends_with( $i_stPath, '/' ) ) {
            $i_stPath = substr( $i_stPath, 0, -1 );
        }
        if ( '' === $i_stPath ) {
            return '/';
        }
        $rPath = explode( '/', $i_stPath );
        return array_pop( $rPath );
    }


    /**
     * Glob for files matching a pattern within a base directory.
     * The base path must be an existing directory. The pattern is
     * a shell-style glob pattern (e.g., "*.txt"). Following standard
     * glob behavior, "*" does not match dotfiles.
     *
     * @return list<string>|Error
     */
    public static function globPattern( string $i_stBasePath, string $i_stPattern ) : array|Error {
        if ( ! file_exists( $i_stBasePath ) ) {
            return Error::PATH_NOT_FOUND;
        }
        if ( ! is_dir( $i_stBasePath ) ) {
            return Error::PATH_IS_FILE;
        }
        return OK::glob( self::mergePath( $i_stBasePath, $i_stPattern ) );
    }


    /** @return list<string>|Error */
    public static function globWild( string $i_stPath ) : array|Error {
        if ( ! file_exists( $i_stPath ) ) {
            return Error::PATH_NOT_FOUND;
        }
        if ( is_dir( $i_stPath ) ) {
            return array_merge( OK::glob( self::mergePath( $i_stPath, '*' ) ), OK::glob( self::mergePath( $i_stPath, '.[!.]*' ) ) );
        }
        if ( is_file( $i_stPath ) ) {
            return [ $i_stPath ];
        }
        return Error::PATH_IS_WEIRD;
    }


    public static function isPathDir( string $i_stPath ) : bool {
        return is_dir( $i_stPath ) && ! is_link( $i_stPath );
    }


    public static function isPathFile( string $i_stPath ) : bool {
        return is_file( $i_stPath ) && ! is_link( $i_stPath );
    }


    public static function isPathSafe( string $i_stPath ) : bool {
        if ( ! str_starts_with( $i_stPath, '/' ) ) {
            return false;
        }
        $i_stPath = trim( $i_stPath, '/' );
        if ( '' === $i_stPath ) {
            # The root path is safe.
            return true;
        }
        foreach ( explode( '/', $i_stPath ) as $stComponent ) {
            if ( '.' === $stComponent || '..' === $stComponent ) {
                return false;
            }
            if ( ! preg_match( '#^[-a-zA-Z0-9.,:!@%^_=+]+$#', $stComponent ) ) {
                return false;
            }
        }
        return true;
    }


    public static function isPathWeird( string $i_stPath ) : bool {
        if ( ! file_exists( $i_stPath ) ) {
            return false;
        }
        if ( is_link( $i_stPath ) ) {
            return true;
        }
        if ( is_dir( $i_stPath ) || is_file( $i_stPath ) ) {
            return false;
        }
        return true;
    }


    /**
     * Check if a pattern string is safe for use in glob operations.
     * Like isPathSafe() but also allows glob wildcard characters
     * (*, ?, [, ]) in path components.
     */
    public static function isPatternSafe( string $i_stPattern ) : bool {
        foreach ( explode( '/', $i_stPattern ) as $stComponent ) {
            if ( '' === $stComponent ) {
                continue;
            }
            if ( '.' === $stComponent || '..' === $stComponent ) {
                return false;
            }
            if ( ! preg_match( '#^[-a-zA-Z0-9.,:!@%^_=+*?\[\]]+$#', $stComponent ) ) {
                return false;
            }
        }
        return true;
    }


    public static function mergePath( string $i_stPath1, string $i_stPath2 ) : string {
        return rtrim( $i_stPath1, '/' ) . '/' . ltrim( $i_stPath2, '/' );
    }


    public static function parentFromPath( string $i_stPath ) : string {
        while ( str_ends_with( $i_stPath, '/' ) ) {
            $i_stPath = substr( $i_stPath, 0, -1 );
        }
        $rPath = explode( '/', $i_stPath );
        $st = implode( '/', array_slice( $rPath, 0, -1 ) );
        if ( '' === $st ) {
            $st = '/';
        }
        return $st;
    }


    /**
     * Recursively find files matching a pattern within a base directory.
     * Uses RecursiveDirectoryIterator to traverse all subdirectories
     * and fnmatch() to filter by the given pattern.
     *
     * For single-component patterns (no "/"), the pattern is matched
     * against the filename only. For multi-component patterns (e.g.,
     * "sub/*.txt"), the pattern is matched against suffixes of the
     * relative path from the base directory at directory boundaries,
     * implementing ** glob semantics. FNM_PERIOD is used in both cases
     * so that dotfile handling is consistent with glob().
     *
     * @return list<string>|Error
     */
    public static function recursiveGlob( string $i_stBasePath, string $i_stPattern ) : array|Error {
        if ( ! file_exists( $i_stBasePath ) ) {
            return Error::PATH_NOT_FOUND;
        }
        if ( ! is_dir( $i_stBasePath ) ) {
            return Error::PATH_IS_FILE;
        }
        $bMultiComponent = str_contains( $i_stPattern, '/' );
        $uBaseLen = strlen( rtrim( $i_stBasePath, '/' ) ) + 1;
        $rOut = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator( $i_stBasePath, \FilesystemIterator::SKIP_DOTS ),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ( $iterator as $stFile ) {
            /** @var \SplFileInfo $stFile */
            if ( $bMultiComponent ) {
                $stRelative = substr( $stFile->getPathname(), $uBaseLen );
                if ( self::matchPathSuffix( $i_stPattern, $stRelative ) ) {
                    $rOut[] = $stFile->getPathname();
                }
            } elseif ( fnmatch( $i_stPattern, $stFile->getFilename(), FNM_PERIOD ) ) {
                $rOut[] = $stFile->getPathname();
            }
        }
        sort( $rOut );
        return $rOut;
    }


    public static function recursiveRemove( string $i_stPath ) : void {
        if ( ! file_exists( $i_stPath ) ) {
            return;
        }
        if ( ! is_dir( $i_stPath ) ) {
            unlink( $i_stPath );
            return;
        }
        $rGlob = self::globWild( $i_stPath );
        if ( ! is_array( $rGlob ) ) {
            return;
        }
        foreach ( $rGlob as $stFile ) {
            self::recursiveRemove( $stFile );
        }
        rmdir( $i_stPath );
    }


    /**
     * Resolves ., .., and multiple slashes in a path. As with
     * Unix, attempts to use ".." from the root silently go back
     * to the root.
     *
     * @param string $i_stPath
     * @return string|Error
     */
    public static function resolvePath( string $i_stPath ) : string|Error {
        if ( ! str_starts_with( $i_stPath, '/' ) ) {
            return Error::PATH_INVALID;
        }
        $rOut = [];
        foreach ( explode( '/', $i_stPath ) as $stComponent ) {
            if ( '.' === $stComponent || '' === $stComponent ) {
                continue;
            }
            if ( '..' === $stComponent ) {
                array_pop( $rOut );
                continue;
            }
            $rOut[] = $stComponent;
        }
        return '/' . implode( '/', $rOut );
    }


    public static function validatePath( string $i_stPath, bool $i_bMustExist = true ) : string|Error {
        if ( ! str_starts_with( $i_stPath, '/' ) ) {
            return Error::PATH_INVALID;
        }
        $rOut = [];
        $stPath = '/';
        $bPathIsWeird = false;
        $bPathIsFile = false;
        $bPathExists = true;
        foreach ( explode( '/', $i_stPath ) as $stComponent ) {
            if ( $bPathIsWeird || $bPathIsFile ) {
                return Error::PATH_PARENT_NOT_DIRECTORY;
            }
            if ( '.' === $stComponent || '' === $stComponent ) {
                continue;
            }
            if ( '..' === $stComponent ) {
                array_pop( $rOut );
                $stPath = '/' . implode( '/', $rOut );
                continue;
            }
            $stPath = self::mergePath( $stPath, $stComponent );
            $rOut[] = $stComponent;
            if ( ! file_exists( $stPath ) ) {
                if ( $i_bMustExist ) {
                    return Error::PATH_NOT_FOUND;
                }
                $bPathExists = false;
                continue;
            }
            if ( is_link( $stPath ) ) {
                $bPathIsWeird = true;
                continue;
            }
            if ( is_file( $stPath ) ) {
                $bPathIsFile = true;
                continue;
            }
            if ( ! is_dir( $stPath ) ) {
                $bPathIsWeird = true;
                continue;
            }
        }
        if ( $bPathIsWeird ) {
            return Error::PATH_IS_WEIRD;
        }
        if ( ! $bPathExists && $i_bMustExist ) {
            return Error::PATH_NOT_FOUND;
        }
        return $stPath;
    }


    /**
     * Check if any suffix of a path (at directory boundaries) matches
     * a pattern. Uses FNM_PATHNAME so that wildcards do not match
     * directory separators, and FNM_PERIOD so that leading dots must
     * be matched explicitly (consistent with glob() dotfile behavior).
     */
    private static function matchPathSuffix( string $i_stPattern, string $i_stPath ) : bool {
        $stPath = $i_stPath;
        while ( true ) {
            if ( fnmatch( $i_stPattern, $stPath, FNM_PATHNAME | FNM_PERIOD ) ) {
                return true;
            }
            $uSlash = strpos( $stPath, '/' );
            if ( false === $uSlash ) {
                return false;
            }
            $stPath = substr( $stPath, $uSlash + 1 );
        }
    }


}
