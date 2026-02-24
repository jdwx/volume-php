<?php


declare( strict_types = 1 );


namespace JDWX\Volume;


use JDWX\Strict\OK;


class Volume {


    private bool $bTemporary;

    private bool $bRemoved = false;

    private string $stPath;


    /**
     * Initialize a volume. The volume is created if it does not exist. If an identifier is
     * given, the volume is persistent and will not be removed when the Volume object is destroyed.
     * If no identifier is given, the volume is temporary and will be removed when the Volume
     * object is destroyed.
     *
     * @param string|null $nstBaseDirectory
     * @param string|null $nstIdentifier
     * @param bool $bReadOnly
     * @param int $uMask File permissions mask for created directories.
     */
    public function __construct( ?string               $nstBaseDirectory = null, ?string $nstIdentifier = null,
                                 private readonly bool $bReadOnly = false, private readonly int $uMask = 0700 ) {
        $nstBaseDirectory ??= sys_get_temp_dir();
        $this->bTemporary = ! is_string( $nstIdentifier );
        $stIdentifier = $nstIdentifier ?? 'temp-volume-' . uniqid( more_entropy: true );
        $stPath = $nstBaseDirectory . '/' . $stIdentifier;
        if ( file_exists( $stPath ) ) {
            if ( ! Helper::isPathDir( $stPath ) ) {
                $stPath = OK::realpath( $stPath );
                throw new \RuntimeException( "Volume path already exists and is not a directory: {$stPath}" );
            }
        } else {
            OK::mkdir( $stPath, $this->uMask, true );
        }
        $this->stPath = rtrim( OK::realpath( $stPath ), '/' );
    }


    public function __destruct() {
        if ( $this->bTemporary ) {
            $this->destroy();
        }
    }


    public function computeRelativeDestinationPath( string $i_stPath, ?string $i_nstFileName = null,
                                                    bool   $i_bAllowCreateParents = true,
                                                    bool   $i_bAllowCreateFile = true ) : string|Error {
        $stPath = $this->computeDestinationPath( $i_stPath, $i_nstFileName,
            $i_bAllowCreateParents, $i_bAllowCreateFile );
        if ( ! is_string( $stPath ) ) {
            return $stPath;
        }
        return substr( $stPath, strlen( $this->stPath ) );
    }


    public function copyIn( string $i_stPathFrom, string $i_stPathTo ) : string|Error {
        if ( ! file_exists( $i_stPathFrom ) ) {
            return Error::PATH_NOT_FOUND;
        }
        $stPathTo = $this->computeDestinationPath( $i_stPathTo, $i_stPathFrom );
        if ( ! is_string( $stPathTo ) ) {
            return $stPathTo;
        }
        $stPathTo = Helper::existingIsError( $stPathTo );
        if ( ! is_string( $stPathTo ) ) {
            return $stPathTo;
        }
        OK::copy( $i_stPathFrom, $stPathTo );
        return $stPathTo;
    }


    /**
     * Destroy a volume, removing all files and directories within it.
     *
     * @return void
     */
    public function destroy() : void {
        if ( $this->bRemoved ) {
            return;
        }
        Helper::recursiveRemove( $this->stPath );
        $this->bRemoved = true;
    }


    /**
     * Check if the volume exists.
     *
     * @return bool True if the volume exists, false if it has been removed.
     */
    public
    function exists() : bool {
        return ! $this->bRemoved;
    }


    /**
     * List the contents of a path, optionally with wildcard matching.
     *
     * Without wildcards: If a filename is given, this returns only that path.
     * If a directory is given, the direct children of that directory are returned.
     *
     * With simple wildcards (e.g., "/a/b/*.txt"): Returns matching entries
     * in the specified directory. Standard glob behavior applies: "*" does
     * not match dotfiles.
     *
     * With recursive wildcards: Use "**" as a path component to recursively
     * search all subdirectories. E.g., list("/a/ ** /*.txt") (without spaces)
     * searches from "/a" for all .txt files at any depth.
     *
     * All paths returned are absolute paths relative to the volume root. E.g.,
     * list("/a/b") returns "/a/b/c" and "/a/b/d." Directories returned will have
     * a trailing slash to differentiate them from files.
     *
     * @return list<string>|Error A list of matching files or an error
     */
    public
    function list( string $i_stPath ) : array|Error {

        if ( ! Helper::containsWildcard( $i_stPath ) ) {
            return $this->listPlain( $i_stPath );
        }

        $rNormalized = $this->normalizePathPattern( $i_stPath );
        if ( ! is_array( $rNormalized ) ) {
            return $rNormalized;
        }
        [ $stBasePath, $stPattern, $bRecursive, $stPrePattern ] = $rNormalized;

        if ( $bRecursive ) {
            $rGlob = $this->recursiveList( $stBasePath, $stPattern, $stPrePattern );
        } else {
            $rGlob = Helper::globPattern( $stBasePath, $stPattern );
        }
        if ( ! is_array( $rGlob ) ) {
            return $rGlob;
        }
        return $this->toRelativePaths( $rGlob );
    }


    public function path() : string {
        return $this->stPath;
    }


    /**
     * Check if a path exists.
     *
     * @param string $i_stPath
     * @return bool True if the path exists, otherwise false
     */
    public function pathExists( string $i_stPath ) : bool {
        $stPath = $this->normalizePathExists( $i_stPath );
        if ( ! is_string( $stPath ) ) {
            return false;
        }
        return true;
    }


    /**
     * Read an existing file.
     *
     * @param string $i_stFilePath
     * @param int|null $i_nuMaxLength
     * @return string|Error
     */
    public function readFile( string $i_stFilePath, ?int $i_nuMaxLength = null ) : string|Error {
        $stPath = $this->normalizePathExistsFile( $i_stFilePath );
        if ( ! is_string( $stPath ) ) {
            return $stPath;
        }
        if ( ! is_int( $i_nuMaxLength ) ) {
            return OK::file_get_contents( $stPath );
        }
        $f = OK::fopen( $stPath, 'rb' );
        $stContents = OK::fread( $f, $i_nuMaxLength );
        OK::fclose( $f );
        return $stContents;
    }


    public function readOnly() : bool {
        return $this->bReadOnly;
    }


    /**
     * Remove a file or directory. If a directory is removed, all contents are also removed.
     * Wildcards are not supported. If the root path is removed, all contents are removed,
     * but the root path itself is not deleted.
     *
     * @param string $i_stPath
     * @return Error|null
     */
    public function remove( string $i_stPath ) : ?Error {
        $stPath = $this->normalizePathExists( $i_stPath );
        if ( ! is_string( $stPath ) ) {
            return $stPath;
        }
        if ( $stPath !== $this->stPath ) {
            Helper::recursiveRemove( $stPath );
            return null;
        }

        # If you remove the top level, we remove everything under it instead.
        $rGlob = Helper::globWild( $stPath );
        if ( ! is_array( $rGlob ) ) {
            return $rGlob;
        }
        foreach ( $rGlob as $stComponent ) {
            Helper::recursiveRemove( $stComponent );
        }
        return null;
    }


    /**
     * Rename an existing file or directory. If the destination path is a directory, the file is moved
     * into that directory with the same name. If the destination path does not exist, it is created.
     *
     * @param string $i_stPathFrom
     * @param string $i_stPathTo
     * @return Error|null
     */
    public function rename( string $i_stPathFrom, string $i_stPathTo ) : ?Error {
        $stPathFrom = $this->normalizePathExistsFile( $i_stPathFrom );
        if ( ! is_string( $stPathFrom ) ) {
            return $stPathFrom;
        }

        $stPathTo = $this->computeDestinationPath( $i_stPathTo, $i_stPathFrom );
        if ( ! is_string( $stPathTo ) ) {
            return $stPathTo;
        }
        if ( Helper::isPathDir( $stPathTo ) ) {
            $stPathTo = Helper::mergePath( $stPathTo, Helper::fileFromPath( $stPathFrom ) );
        }
        if ( file_exists( $stPathTo ) ) {
            return Error::PATH_EXISTS;
        }
        OK::rename( $stPathFrom, $stPathTo );
        return null;
    }


    /**
     * Write content to a file, replacing any file that already exists with that name.
     * Creates any parent directories as needed.
     *
     * @param string $i_stFilePath
     * @param string $i_stContents
     * @return Error|null
     */
    public function replaceFile( string $i_stFilePath, string $i_stContents ) : ?Error {
        $stPathParent = $this->normalizePathParentExists( $i_stFilePath, true );
        if ( ! is_string( $stPathParent ) ) {
            return $stPathParent;
        }
        $stPath = $this->normalizePath( $i_stFilePath );
        if ( ! is_string( $stPath ) ) {
            return $stPath;
        }
        if ( ! file_exists( $stPath ) || Helper::isPathFile( $stPath ) ) {
            OK::file_put_contents( $stPath, $i_stContents );
            return null;
        }
        if ( Helper::isPathDir( $stPath ) ) {
            return Error::PATH_IS_DIRECTORY;
        }
        return Error::PATH_IS_WEIRD;
    }


    /**
     * Write content to a file, creating any parent directories as needed.
     *
     * @param string $i_stFilePath
     * @param string $i_stContents
     * @return Error|null
     */
    public function writeFile( string $i_stFilePath, string $i_stContents ) : ?Error {
        $stPath = $this->normalizePathParentExists( $i_stFilePath, true );
        if ( ! is_string( $stPath ) ) {
            return $stPath;
        }
        $stFile = Helper::fileFromPath( $i_stFilePath );
        $stPath = Helper::mergePath( $stPath, $stFile );
        if ( file_exists( $stPath ) ) {
            return Error::PATH_EXISTS;
        }
        OK::file_put_contents( $stPath, $i_stContents );
        return null;
    }


    /**
     * The purpose of this function is to calculate the correct path to be used as the
     * destination for a file operation. It handles the following cases:
     *
     * - The given path doesn't exist at all. (It is inferred to be a filename in a nonexistent directory.)
     * - The given path is an existing directory and a filename is also provided.
     * - The given path is a nonexistent name (inferred to be a filename) in an existing directory.
     *
     * It returns errors when:
     * - The given path is invalid. (Error::PATH_INVALID)
     * - The given path is a directory and no filename is provided. (Error::PATH_NOT_FOUND)
     * - The given path (or the given path plus filename) resolves to something that isn't a file. (Error::PATH_NOT_FOUND)
     *
     * The caller can specify whether to allow creation of parent directories if needed and
     * whether the file must already exist. Error::PATH_NOT_FOUND will be returned if
     * something cannot be created.
     *
     * Anytime $i_nstFileName is appended, any path components other than the filename will
     * be omitted. This makes it easy to take the filename from a source path, e.g., for a
     * copy or rename operation.
     *
     * A path returned by this function is not guaranteed to exist. It is simply guaranteed
     * to be valid as a filename at the time of the call.
     *
     * @param string $i_stPath
     * @param string|null $i_nstFileName
     * @param bool $i_bAllowCreateParents If true, parent directories may be created as needed.
     * @param bool $i_bAllowCreateFile If false, the computed path must exist as a file.
     * @return string|Error
     */
    private function computeDestinationPath( string $i_stPath, ?string $i_nstFileName = null,
                                             bool   $i_bAllowCreateParents = true,
                                             bool   $i_bAllowCreateFile = true ) : string|Error {

        $stRelativePath = $this->relativePath( $i_stPath );
        if ( ! is_string( $stRelativePath ) ) {
            return $stRelativePath;
        }

        $stRelativePath = $this->validateRelativePath( $stRelativePath, false );
        if ( $stRelativePath instanceof Error ) {
            return $stRelativePath;
        }

        $stPath = Helper::mergePath( $this->stPath, $stRelativePath );
        if ( ! file_exists( $stPath ) ) {
            if ( ! $i_bAllowCreateFile ) {
                return Error::PATH_NOT_FOUND;
            }
            $stParentPath = Helper::parentFromPath( $stPath );
            if ( ! file_exists( $stParentPath ) ) {
                if ( ! $i_bAllowCreateParents ) {
                    return Error::PATH_NOT_FOUND;
                }
                OK::mkdir( $stParentPath, $this->uMask, true );
            }
            return $stPath;
        }

        if ( Helper::isPathFile( $stPath ) ) {
            return $stPath;
        }

        if ( ! Helper::isPathDir( $stPath ) ) {
            return Error::PATH_IS_WEIRD;
        }

        if ( ! is_string( $i_nstFileName ) ) {
            return Error::PATH_NOT_FOUND;
        }

        if ( ! $i_bAllowCreateFile ) {
            return Error::PATH_IS_DIRECTORY;
        }

        $stPath = Helper::mergePath( $stPath, Helper::fileFromPath( $i_nstFileName ) );

        if ( ! file_exists( $stPath ) || Helper::isPathFile( $stPath ) ) {
            return $stPath;
        }

        return Helper::isPathDir( $stPath ) ? Error::PATH_IS_DIRECTORY : Error::PATH_IS_WEIRD;
    }


    /** @return list<string>|Error */
    private function listPlain( string $i_stPath ) : array|Error {
        $stPath = $this->normalizePathExists( $i_stPath );
        if ( ! is_string( $stPath ) ) {
            return $stPath;
        }
        $rGlob = Helper::globWild( $stPath );
        if ( ! is_array( $rGlob ) ) {
            return $rGlob;
        }
        return $this->toRelativePaths( $rGlob );
    }


    private function normalizePath( string $i_stPath ) : string|Error {
        if ( $this->bRemoved ) {
            return Error::DIRECTORY_IS_CLOSED;
        }

        $stPath = Helper::resolvePath( $i_stPath );
        if ( ! is_string( $stPath ) ) {
            return $stPath;
        }

        if ( ! Helper::isPathSafe( $stPath ) ) {
            return Error::PATH_INVALID;
        }

        return $this->stPath . ( '/' === $stPath ? '' : $stPath );
    }


    private function normalizePathExists( string $i_stPath ) : string|Error {
        $stPath = $this->normalizePath( $i_stPath );
        if ( ! is_string( $stPath ) ) {
            return $stPath;
        }
        if ( ! file_exists( $stPath ) ) {
            return Error::PATH_NOT_FOUND;
        }
        return $stPath;
    }


    private function normalizePathExistsFile( string $i_stPath ) : string|Error {
        $stPath = $this->normalizePathExists( $i_stPath );
        if ( ! is_string( $stPath ) ) {
            return $stPath;
        }
        if ( Helper::isPathFile( $stPath ) ) {
            return $stPath;
        }
        if ( Helper::isPathDir( $stPath ) ) {
            return Error::PATH_IS_DIRECTORY;
        }
        return Error::PATH_IS_WEIRD;
    }


    private function normalizePathParent( string $i_stPath ) : string|Error {
        if ( $this->bRemoved ) {
            return Error::DIRECTORY_IS_CLOSED;
        }

        $stPath = Helper::resolvePath( $i_stPath );
        if ( ! is_string( $stPath ) ) {
            return $stPath;
        }

        if ( ! str_starts_with( $stPath, '/' ) ) {
            return Error::PATH_INVALID;
        }
        $stPath = Helper::parentFromPath( $stPath );
        return rtrim( Helper::mergePath( $this->stPath, $stPath ), '/' );
    }


    private function normalizePathParentExists( string $i_stPath, bool $i_bAllowCreate ) : string|Error {
        $stPath = $this->normalizePathParent( $i_stPath );
        if ( ! is_string( $stPath ) ) {
            return $stPath;
        }
        $stRest = trim( substr( $stPath, strlen( $this->stPath ) ), '/' );
        if ( '' === $stRest ) {
            return $this->stPath;
        }
        $stWorkingPath = $this->stPath;

        foreach ( explode( '/', $stRest ) as $stComponent ) {
            $stWorkingPath = Helper::mergePath( $stWorkingPath, $stComponent );
            if ( ! file_exists( $stWorkingPath ) ) {
                if ( $i_bAllowCreate ) {
                    OK::mkdir( $stPath, $this->uMask, true );
                    return $stPath;
                }
                return Error::PATH_PARENT_NOT_FOUND;
            }
            if ( ! Helper::isPathDir( $stWorkingPath ) ) {
                return Error::PATH_PARENT_NOT_DIRECTORY;
            }
        }

        return $stWorkingPath;
    }


    /**
     * Normalize a path containing wildcards. Returns an array of
     * [basePath, pattern, isRecursive] or an Error.
     *
     * The path is first resolved (., .., multiple slashes), then split
     * at the first component containing a wildcard. The base directory
     * is validated through normal normalization; the pattern is validated
     * with isPatternSafe().
     *
     * @return array{string, string, bool, string}|Error
     */
    private function normalizePathPattern( string $i_stPath ) : array|Error {
        if ( $this->bRemoved ) {
            return Error::DIRECTORY_IS_CLOSED;
        }

        # Resolve ., .., and multiple slashes first.
        $stResolved = Helper::resolvePath( $i_stPath );
        if ( ! is_string( $stResolved ) ) {
            return $stResolved;
        }

        # Split into components and find where the wildcard starts.
        $rComponents = explode( '/', ltrim( $stResolved, '/' ) );
        $rBaseParts = [];
        $rPatternParts = [];
        $bFoundWild = false;
        foreach ( $rComponents as $stComponent ) {
            if ( ! $bFoundWild && '**' !== $stComponent && ! Helper::containsWildcard( $stComponent ) ) {
                $rBaseParts[] = $stComponent;
            } else {
                $bFoundWild = true;
                $rPatternParts[] = $stComponent;
            }
        }

        # Build the base path and validate it.
        $stBasePath = '/' . implode( '/', $rBaseParts );
        if ( ! Helper::isPathSafe( $stBasePath ) ) {
            return Error::PATH_INVALID;
        }
        $stAbsBase = $this->stPath . ( '/' === $stBasePath ? '' : $stBasePath );
        if ( ! file_exists( $stAbsBase ) || ! Helper::isPathDir( $stAbsBase ) ) {
            return Error::PATH_NOT_FOUND;
        }

        # Validate the pattern portion.
        $stPattern = implode( '/', $rPatternParts );
        if ( ! Helper::isPatternSafe( $stPattern ) ) {
            return Error::PATH_INVALID;
        }

        # Detect recursive glob (contains "**").
        $bRecursive = in_array( '**', $rPatternParts, true );
        $stPrePattern = '';
        if ( $bRecursive ) {
            # Split pattern parts into before and after "**".
            $rBeforeStar = [];
            $rAfterStar = [];
            $bPastDoubleStar = false;
            foreach ( $rPatternParts as $stPart ) {
                if ( '**' === $stPart ) {
                    $bPastDoubleStar = true;
                    continue;
                }
                if ( $bPastDoubleStar ) {
                    $rAfterStar[] = $stPart;
                } else {
                    $rBeforeStar[] = $stPart;
                }
            }
            $stPattern = implode( '/', $rAfterStar );
            if ( '' === $stPattern ) {
                $stPattern = '*';
            }
            $stPrePattern = implode( '/', $rBeforeStar );
        }

        return [ $stAbsBase, $stPattern, $bRecursive, $stPrePattern ];
    }


    /** @return list<string>|Error */
    private function recursiveList( string $i_stBasePath, string $i_stPattern, string $i_stPrePattern ) : array|Error {
        if ( '' === $i_stPrePattern ) {
            return Helper::recursiveGlob( $i_stBasePath, $i_stPattern );
        }
        $rBaseDirs = OK::glob( Helper::mergePath( $i_stBasePath, $i_stPrePattern ) );
        $rBaseDirs = array_filter( $rBaseDirs, 'JDWX\Volume\Helper::isPathDir' );
        $rOut = [];
        foreach ( $rBaseDirs as $stDir ) {
            $rGlob = Helper::recursiveGlob( $stDir, $i_stPattern );
            if ( is_array( $rGlob ) ) {
                array_push( $rOut, ...$rGlob );
            }
        }
        sort( $rOut );
        return $rOut;
    }


    private function relativePath( string $i_stPath ) : string|Error {
        $stPath = $this->normalizePath( $i_stPath );
        if ( ! is_string( $stPath ) ) {
            return $stPath;
        }

        $stPath = substr( $stPath, strlen( $this->path() ) );
        if ( '' === $stPath ) {
            return '/';
        }
        return $stPath;
    }


    /**
     * Convert a list of absolute filesystem paths to volume-relative paths.
     * Directories get a trailing slash.
     *
     * @param list<string> $i_rPaths
     * @return list<string>
     */
    private function toRelativePaths( array $i_rPaths ) : array {
        $rOut = [];
        $uSkip = strlen( $this->stPath );
        foreach ( $i_rPaths as $stMatch ) {
            if ( Helper::isPathDir( $stMatch ) ) {
                $stMatch .= '/';
            }
            $rOut[] = substr( $stMatch, $uSkip );
        }
        return $rOut;
    }


    private function validateRelativePath( string $i_stPath, bool $i_bMustExist = true ) : string|Error {
        $stPath = $this->stPath;
        $bFoundFile = false;
        $bFoundWeird = false;
        foreach ( explode( '/', trim( $i_stPath, '/' ) ) as $stComponent ) {
            if ( $bFoundFile || $bFoundWeird ) {
                return Error::PATH_PARENT_NOT_DIRECTORY;
            }
            $stPath = Helper::mergePath( $stPath, $stComponent );
            if ( ! file_exists( $stPath ) ) {
                if ( $i_bMustExist ) {
                    return Error::PATH_NOT_FOUND;
                }
                break;
            }
            if ( Helper::isPathFile( $stPath ) ) {
                $bFoundFile = true;
                continue;
            }
            if ( ! Helper::isPathDir( $stPath ) ) {
                $bFoundWeird = true;
                // continue; # Implicit.
            }
        }
        if ( $bFoundWeird ) {
            return Error::PATH_IS_WEIRD;
        }
        return $i_stPath;
    }


}
