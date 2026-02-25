<?php


declare( strict_types = 1 );


namespace JDWX\Volume\Tests;


use JDWX\Strict\OK;
use JDWX\Strict\TypeIs;
use JDWX\Volume\Error;
use JDWX\Volume\Helper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;


#[CoversClass( Helper::class )]
class HelperTest extends TestCase {


    /** @var list<string> Filesystem paths to clean up after each test. */
    private array $rCleanupPaths = [];


    private static function recursiveDelete( string $i_stPath ) : void {
        if ( is_link( $i_stPath ) ) {
            unlink( $i_stPath );
            return;
        }
        if ( ! file_exists( $i_stPath ) ) {
            return;
        }
        if ( is_dir( $i_stPath ) ) {
            $rItems = array_diff( OK::scandir( $i_stPath ), [ '.', '..' ] );
            foreach ( $rItems as $stItem ) {
                self::recursiveDelete( $i_stPath . '/' . $stItem );
            }
            rmdir( $i_stPath );
        } else {
            unlink( $i_stPath );
        }
    }


    public function testContainsWildcardBracket() : void {
        self::assertTrue( Helper::containsWildcard( '/a/file[0-9].txt' ) );
    }


    public function testContainsWildcardDoubleStar() : void {
        self::assertTrue( Helper::containsWildcard( '/a/**/*.txt' ) );
    }


    public function testContainsWildcardNone() : void {
        self::assertFalse( Helper::containsWildcard( '/a/b/c.txt' ) );
    }


    public function testContainsWildcardQuestion() : void {
        self::assertTrue( Helper::containsWildcard( '/a/file?.txt' ) );
    }


    public function testContainsWildcardStar() : void {
        self::assertTrue( Helper::containsWildcard( '/a/*.txt' ) );
    }


    public function testExistingIsErrorForDirectory() : void {
        $stDir = $this->tempDir();
        self::assertSame( Error::PATH_IS_DIRECTORY, Helper::existingIsError( $stDir ) );
    }


    public function testExistingIsErrorForFifo() : void {
        $stDir = $this->tempDir();
        $stFifo = $stDir . '/test.fifo';
        posix_mkfifo( $stFifo, 0600 );
        self::assertSame( Error::PATH_IS_WEIRD, Helper::existingIsError( $stFifo ) );
    }


    public function testExistingIsErrorForFile() : void {
        $stDir = $this->tempDir();
        $stFile = $stDir . '/exists.txt';
        file_put_contents( $stFile, 'x' );
        self::assertSame( Error::PATH_IS_FILE, Helper::existingIsError( $stFile ) );
    }


    public function testExistingIsErrorForNonexistent() : void {
        $stDir = $this->tempDir();
        $stPath = $stDir . '/nonexistent.txt';
        self::assertSame( $stPath, Helper::existingIsError( $stPath ) );
    }


    public function testExistingIsErrorForDanglingSymlink() : void {
        $stDir = $this->tempDir();
        $stLink = $stDir . '/dangling';
        symlink( $stDir . '/nonexistent-target', $stLink );
        # Dangling symlinks fail file_exists(), so they are treated as nonexistent.
        self::assertSame( $stLink, Helper::existingIsError( $stLink ) );
    }


    public function testExistingIsErrorForSymlinkToDirectory() : void {
        $stDir = $this->tempDir();
        $stTarget = $stDir . '/target-dir';
        mkdir( $stTarget );
        $stLink = $stDir . '/link';
        symlink( $stTarget, $stLink );
        self::assertSame( Error::PATH_IS_WEIRD, Helper::existingIsError( $stLink ) );
    }


    public function testExistingIsErrorForSymlinkToFile() : void {
        $stDir = $this->tempDir();
        $stTarget = $stDir . '/target.txt';
        file_put_contents( $stTarget, 'x' );
        $stLink = $stDir . '/link';
        symlink( $stTarget, $stLink );
        self::assertSame( Error::PATH_IS_WEIRD, Helper::existingIsError( $stLink ) );
    }


    public function testFileFromPath() : void {
        self::assertSame( 'file.txt', Helper::fileFromPath( '/a/b/file.txt' ) );
    }


    public function testFileFromPathRootSlash() : void {
        self::assertSame( '/', Helper::fileFromPath( '/' ) );
    }


    public function testFileFromPathMultipleTrailingSlashes() : void {
        self::assertSame( 'dir', Helper::fileFromPath( '/a/dir///' ) );
    }


    public function testFileFromPathSingleComponent() : void {
        self::assertSame( 'file.txt', Helper::fileFromPath( 'file.txt' ) );
    }


    public function testFileFromPathTrailingSlash() : void {
        self::assertSame( 'dir', Helper::fileFromPath( '/a/b/dir/' ) );
    }


    public function testGlobPatternBaseIsFile() : void {
        $stDir = $this->tempDir();
        $stFile = $stDir . '/a.txt';
        file_put_contents( $stFile, 'a' );
        self::assertSame( Error::PATH_IS_FILE, Helper::globPattern( $stFile, '*.txt' ) );
    }


    public function testGlobPatternDoesNotMatchDotfiles() : void {
        $stDir = $this->tempDir();
        file_put_contents( $stDir . '/.hidden', 'a' );
        file_put_contents( $stDir . '/visible.txt', 'b' );
        $result = Helper::globPattern( $stDir, '*' );
        $result = TypeIs::array( $result );
        self::assertContains( $stDir . '/visible.txt', $result );
        self::assertNotContains( $stDir . '/.hidden', $result );
    }


    public function testGlobPatternMatchesTxt() : void {
        $stDir = $this->tempDir();
        file_put_contents( $stDir . '/a.txt', 'a' );
        file_put_contents( $stDir . '/b.log', 'b' );
        $result = Helper::globPattern( $stDir, '*.txt' );
        $result = TypeIs::array( $result );
        self::assertSame( [ $stDir . '/a.txt' ], $result );
    }


    public function testGlobPatternNoMatches() : void {
        $stDir = $this->tempDir();
        file_put_contents( $stDir . '/a.txt', 'a' );
        $result = Helper::globPattern( $stDir, '*.log' );
        $result = TypeIs::array( $result );
        self::assertSame( [], $result );
    }


    public function testGlobPatternNonexistentBase() : void {
        self::assertSame( Error::PATH_NOT_FOUND, Helper::globPattern( '/nonexistent', '*.txt' ) );
    }


    public function testGlobWildDirectoryWithFiles() : void {
        $stDir = $this->tempDir();
        file_put_contents( $stDir . '/a.txt', 'a' );
        file_put_contents( $stDir . '/b.txt', 'b' );
        $result = Helper::globWild( $stDir );
        $result = TypeIs::array( $result );
        self::assertCount( 2, $result );
        self::assertContains( $stDir . '/a.txt', $result );
        self::assertContains( $stDir . '/b.txt', $result );
    }


    public function testGlobWildEmptyDirectory() : void {
        $stDir = $this->tempDir();
        $result = Helper::globWild( $stDir );
        $result = TypeIs::array( $result );
        self::assertSame( [], $result );
    }


    public function testGlobWildExcludesDotAndDotDot() : void {
        $stDir = $this->tempDir();
        file_put_contents( $stDir . '/a.txt', 'a' );
        $result = Helper::globWild( $stDir );
        $result = TypeIs::array( $result );
        self::assertNotContains( $stDir . '/.', $result );
        self::assertNotContains( $stDir . '/..', $result );
        self::assertNotContains( '.', $result );
        self::assertNotContains( '..', $result );
    }


    public function testGlobWildFile() : void {
        $stDir = $this->tempDir();
        $stFile = $stDir . '/single.txt';
        file_put_contents( $stFile, 'x' );
        $result = Helper::globWild( $stFile );
        $result = TypeIs::array( $result );
        self::assertSame( [ $stFile ], $result );
    }


    public function testGlobWildIncludesHiddenFiles() : void {
        $stDir = $this->tempDir();
        file_put_contents( $stDir . '/visible.txt', 'a' );
        file_put_contents( $stDir . '/.hidden', 'b' );
        $result = Helper::globWild( $stDir );
        $result = TypeIs::array( $result );
        self::assertContains( $stDir . '/visible.txt', $result );
        self::assertContains( $stDir . '/.hidden', $result );
    }


    public function testGlobWildFifo() : void {
        $stDir = $this->tempDir();
        $stFifo = $stDir . '/test.fifo';
        posix_mkfifo( $stFifo, 0600 );
        self::assertSame( Error::PATH_IS_WEIRD, Helper::globWild( $stFifo ) );
    }


    public function testGlobWildNonexistent() : void {
        self::assertSame( Error::PATH_NOT_FOUND, Helper::globWild( '/nonexistent/path' ) );
    }


    public function testIsPathDirForDirectory() : void {
        $stDir = $this->tempDir();
        self::assertTrue( Helper::isPathDir( $stDir ) );
    }


    public function testIsPathDirForFile() : void {
        $stDir = $this->tempDir();
        $stFile = $stDir . '/file.txt';
        file_put_contents( $stFile, 'x' );
        self::assertFalse( Helper::isPathDir( $stFile ) );
    }


    public function testIsPathDirForFifo() : void {
        $stDir = $this->tempDir();
        $stFifo = $stDir . '/test.fifo';
        posix_mkfifo( $stFifo, 0600 );
        self::assertFalse( Helper::isPathDir( $stFifo ) );
    }


    public function testIsPathDirForNonexistent() : void {
        self::assertFalse( Helper::isPathDir( '/nonexistent/path' ) );
    }


    public function testIsPathDirForSymlinkToDirectory() : void {
        $stDir = $this->tempDir();
        $stTarget = $stDir . '/target-dir';
        mkdir( $stTarget );
        $stLink = $stDir . '/link';
        symlink( $stTarget, $stLink );
        self::assertFalse( Helper::isPathDir( $stLink ) );
    }


    public function testIsPathDirForSymlinkToFile() : void {
        $stDir = $this->tempDir();
        $stTarget = $stDir . '/target.txt';
        file_put_contents( $stTarget, 'x' );
        $stLink = $stDir . '/link';
        symlink( $stTarget, $stLink );
        self::assertFalse( Helper::isPathDir( $stLink ) );
    }


    public function testIsPathFileForDirectory() : void {
        $stDir = $this->tempDir();
        self::assertFalse( Helper::isPathFile( $stDir ) );
    }


    public function testIsPathFileForFifo() : void {
        $stDir = $this->tempDir();
        $stFifo = $stDir . '/test.fifo';
        posix_mkfifo( $stFifo, 0600 );
        self::assertFalse( Helper::isPathFile( $stFifo ) );
    }


    public function testIsPathFileForFile() : void {
        $stDir = $this->tempDir();
        $stFile = $stDir . '/file.txt';
        file_put_contents( $stFile, 'x' );
        self::assertTrue( Helper::isPathFile( $stFile ) );
    }


    public function testIsPathFileForNonexistent() : void {
        self::assertFalse( Helper::isPathFile( '/nonexistent/path' ) );
    }


    public function testIsPathFileForSymlinkToDirectory() : void {
        $stDir = $this->tempDir();
        $stTarget = $stDir . '/target-dir';
        mkdir( $stTarget );
        $stLink = $stDir . '/link';
        symlink( $stTarget, $stLink );
        self::assertFalse( Helper::isPathFile( $stLink ) );
    }


    public function testIsPathFileForSymlinkToFile() : void {
        $stDir = $this->tempDir();
        $stTarget = $stDir . '/target.txt';
        file_put_contents( $stTarget, 'x' );
        $stLink = $stDir . '/link';
        symlink( $stTarget, $stLink );
        self::assertFalse( Helper::isPathFile( $stLink ) );
    }


    public function testIsPathSafeAllowsDots() : void {
        self::assertTrue( Helper::isPathSafe( '/file.tar.gz' ) );
    }


    public function testIsPathSafeAllowsHyphen() : void {
        self::assertTrue( Helper::isPathSafe( '/my-file' ) );
    }


    public function testIsPathSafeAllowsSpecialChars() : void {
        self::assertTrue( Helper::isPathSafe( '/a,:!@%^=+' ) );
    }


    public function testIsPathSafeAllowsUnderscore() : void {
        self::assertTrue( Helper::isPathSafe( '/my_file' ) );
    }


    public function testIsPathSafeDeepPath() : void {
        self::assertTrue( Helper::isPathSafe( '/usr/local/bin/script' ) );
    }


    public function testIsPathSafeNormalPath() : void {
        self::assertTrue( Helper::isPathSafe( '/a/b/c' ) );
    }


    public function testIsPathSafeRejectsDot() : void {
        self::assertFalse( Helper::isPathSafe( '/a/./b' ) );
    }


    public function testIsPathSafeRejectsDoubleDot() : void {
        self::assertFalse( Helper::isPathSafe( '/a/../b' ) );
    }


    public function testIsPathSafeRejectsEmptyString() : void {
        self::assertFalse( Helper::isPathSafe( '' ) );
    }


    public function testIsPathSafeRejectsRelativePath() : void {
        self::assertFalse( Helper::isPathSafe( 'a/b/c' ) );
    }


    public function testIsPathSafeRejectsShellMetacharacters() : void {
        self::assertFalse( Helper::isPathSafe( '/file$(cmd)' ) );
        self::assertFalse( Helper::isPathSafe( '/file;rm' ) );
        self::assertFalse( Helper::isPathSafe( '/file|cat' ) );
        self::assertFalse( Helper::isPathSafe( '/file&bg' ) );
    }


    public function testIsPathSafeRejectsSpace() : void {
        self::assertFalse( Helper::isPathSafe( '/a/b c' ) );
    }


    public function testIsPathSafeRejectsTrailingSlash() : void {
        // After trimming slashes, a path like "/a/b/" becomes "a/b"
        // which is valid. The trailing slash should not cause rejection.
        self::assertTrue( Helper::isPathSafe( '/a/b/' ) );
    }


    public function testIsPathSafeRoot() : void {
        self::assertTrue( Helper::isPathSafe( '/' ) );
    }


    public function testIsPathWeirdForDirectory() : void {
        $stDir = $this->tempDir();
        self::assertFalse( Helper::isPathWeird( $stDir ) );
    }


    public function testIsPathWeirdForFifo() : void {
        $stDir = $this->tempDir();
        $stFifo = $stDir . '/test.fifo';
        posix_mkfifo( $stFifo, 0600 );
        self::assertTrue( Helper::isPathWeird( $stFifo ) );
    }


    public function testIsPathWeirdForFile() : void {
        $stDir = $this->tempDir();
        $stFile = $stDir . '/file.txt';
        file_put_contents( $stFile, 'x' );
        self::assertFalse( Helper::isPathWeird( $stFile ) );
    }


    public function testIsPathWeirdForNonexistent() : void {
        self::assertFalse( Helper::isPathWeird( '/nonexistent/path' ) );
    }


    public function testIsPathWeirdForSymlinkToDirectory() : void {
        $stDir = $this->tempDir();
        $stTarget = $stDir . '/target-dir';
        mkdir( $stTarget );
        $stLink = $stDir . '/link';
        symlink( $stTarget, $stLink );
        self::assertTrue( Helper::isPathWeird( $stLink ) );
    }


    public function testIsPathWeirdForSymlinkToFile() : void {
        $stDir = $this->tempDir();
        $stTarget = $stDir . '/target.txt';
        file_put_contents( $stTarget, 'x' );
        $stLink = $stDir . '/link';
        symlink( $stTarget, $stLink );
        self::assertTrue( Helper::isPathWeird( $stLink ) );
    }


    public function testIsPatternSafeAllowsDoubleStar() : void {
        self::assertTrue( Helper::isPatternSafe( '**/*.txt' ) );
    }


    public function testIsPatternSafeAllowsNormalPath() : void {
        self::assertTrue( Helper::isPatternSafe( 'a/b/c' ) );
    }


    public function testIsPatternSafeAllowsWildcards() : void {
        self::assertTrue( Helper::isPatternSafe( '*.txt' ) );
        self::assertTrue( Helper::isPatternSafe( 'file?.log' ) );
        self::assertTrue( Helper::isPatternSafe( 'file[0-9].txt' ) );
    }


    public function testIsPatternSafeEmptyComponents() : void {
        # Empty components from leading/trailing/multiple slashes should be skipped.
        self::assertTrue( Helper::isPatternSafe( '/a//b/' ) );
    }


    public function testIsPatternSafeRejectsDot() : void {
        self::assertFalse( Helper::isPatternSafe( 'a/./b' ) );
    }


    public function testIsPatternSafeRejectsDoubleDot() : void {
        self::assertFalse( Helper::isPatternSafe( 'a/../b' ) );
    }


    public function testIsPatternSafeRejectsShellMetacharacters() : void {
        self::assertFalse( Helper::isPatternSafe( '$(cmd)' ) );
        self::assertFalse( Helper::isPatternSafe( 'file;rm' ) );
        self::assertFalse( Helper::isPatternSafe( 'file|cat' ) );
        self::assertFalse( Helper::isPatternSafe( 'file&bg' ) );
    }


    public function testIsPatternSafeRejectsSpace() : void {
        self::assertFalse( Helper::isPatternSafe( 'a b' ) );
    }


    public function testMergePath() : void {
        self::assertSame( '/a/b', Helper::mergePath( '/a', 'b' ) );
    }


    public function testMergePathBothSlashes() : void {
        // mergePath only normalizes the join point; trailing slash on the
        // second argument is preserved.
        self::assertSame( '/a/b/', Helper::mergePath( '/a/', '/b/' ) );
    }


    public function testMergePathTrimsSlashes() : void {
        self::assertSame( '/a/b', Helper::mergePath( '/a/', '/b' ) );
    }


    public function testMergePathWithGlobPattern() : void {
        self::assertSame( '/dir/*', Helper::mergePath( '/dir', '*' ) );
    }


    public function testParentFromPath() : void {
        self::assertSame( '/a/b', Helper::parentFromPath( '/a/b/c' ) );
    }


    public function testParentFromPathMultipleTrailingSlashes() : void {
        self::assertSame( '/a', Helper::parentFromPath( '/a/b///' ) );
    }


    public function testParentFromPathRoot() : void {
        self::assertSame( '/', Helper::parentFromPath( '/' ) );
    }


    public function testParentFromPathRootChild() : void {
        self::assertSame( '/', Helper::parentFromPath( '/a' ) );
    }


    public function testParentFromPathTrailingSlash() : void {
        self::assertSame( '/a', Helper::parentFromPath( '/a/b/' ) );
    }


    public function testRecursiveGlobBaseIsFile() : void {
        $stDir = $this->tempDir();
        $stFile = $stDir . '/a.txt';
        file_put_contents( $stFile, 'a' );
        self::assertSame( Error::PATH_IS_FILE, Helper::recursiveGlob( $stFile, '*.txt' ) );
    }


    public function testRecursiveGlobFiltersByPattern() : void {
        $stDir = $this->tempDir();
        mkdir( $stDir . '/sub', 0700 );
        file_put_contents( $stDir . '/a.txt', 'a' );
        file_put_contents( $stDir . '/b.log', 'b' );
        file_put_contents( $stDir . '/sub/c.txt', 'c' );
        file_put_contents( $stDir . '/sub/d.log', 'd' );
        $result = Helper::recursiveGlob( $stDir, '*.txt' );
        $result = TypeIs::array( $result );
        self::assertCount( 2, $result );
        self::assertContains( $stDir . '/a.txt', $result );
        self::assertContains( $stDir . '/sub/c.txt', $result );
    }


    public function testRecursiveGlobFindsDeepFiles() : void {
        $stDir = $this->tempDir();
        mkdir( $stDir . '/a/b', 0700, true );
        file_put_contents( $stDir . '/top.txt', 'top' );
        file_put_contents( $stDir . '/a/mid.txt', 'mid' );
        file_put_contents( $stDir . '/a/b/deep.txt', 'deep' );
        $result = Helper::recursiveGlob( $stDir, '*.txt' );
        $result = TypeIs::array( $result );
        self::assertCount( 3, $result );
        self::assertContains( $stDir . '/top.txt', $result );
        self::assertContains( $stDir . '/a/mid.txt', $result );
        self::assertContains( $stDir . '/a/b/deep.txt', $result );
    }


    public function testRecursiveGlobMultiComponentPattern() : void {
        $stDir = $this->tempDir();
        mkdir( $stDir . '/a/sub', 0700, true );
        mkdir( $stDir . '/a/other', 0700, true );
        file_put_contents( $stDir . '/a/sub/file.txt', 'in sub' );
        file_put_contents( $stDir . '/a/other/file.txt', 'in other' );
        // A multi-component pattern like "sub/*.txt" should match files
        // within "sub" directories, not just filenames matching "sub/*.txt".
        // Current bug: fnmatch() is applied against the filename only, so
        // "sub/*.txt" never matches a bare filename like "file.txt".
        $result = Helper::recursiveGlob( $stDir, 'sub/*.txt' );
        $result = TypeIs::array( $result );
        self::assertContains( $stDir . '/a/sub/file.txt', $result );
        self::assertNotContains( $stDir . '/a/other/file.txt', $result );
    }


    public function testRecursiveGlobDoesNotMatchDotfiles() : void {
        $stDir = $this->tempDir();
        mkdir( $stDir . '/sub', 0700 );
        file_put_contents( $stDir . '/visible.txt', 'a' );
        file_put_contents( $stDir . '/.hidden.txt', 'b' );
        file_put_contents( $stDir . '/sub/.secret.txt', 'c' );
        $result = Helper::recursiveGlob( $stDir, '*.txt' );
        $result = TypeIs::array( $result );
        self::assertContains( $stDir . '/visible.txt', $result );
        self::assertNotContains( $stDir . '/.hidden.txt', $result );
        self::assertNotContains( $stDir . '/sub/.secret.txt', $result );
    }


    public function testRecursiveGlobNoMatches() : void {
        $stDir = $this->tempDir();
        file_put_contents( $stDir . '/a.txt', 'a' );
        $result = Helper::recursiveGlob( $stDir, '*.xyz' );
        $result = TypeIs::array( $result );
        self::assertSame( [], $result );
    }


    public function testRecursiveGlobNonexistentBase() : void {
        self::assertSame( Error::PATH_NOT_FOUND, Helper::recursiveGlob( '/nonexistent', '*.txt' ) );
    }


    public function testRecursiveRemoveDirectory() : void {
        $stDir = $this->tempDir();
        $stSub = $stDir . '/sub';
        mkdir( $stSub );
        file_put_contents( $stSub . '/a.txt', 'a' );
        file_put_contents( $stSub . '/b.txt', 'b' );
        Helper::recursiveRemove( $stSub );
        self::assertFalse( file_exists( $stSub ) );
    }


    public function testRecursiveRemoveFile() : void {
        $stDir = $this->tempDir();
        $stFile = $stDir . '/remove-me.txt';
        file_put_contents( $stFile, 'bye' );
        Helper::recursiveRemove( $stFile );
        self::assertFalse( file_exists( $stFile ) );
    }


    public function testRecursiveRemoveNestedDirectories() : void {
        $stDir = $this->tempDir();
        $stDeep = $stDir . '/a/b/c';
        mkdir( $stDeep, 0700, true );
        file_put_contents( $stDeep . '/file.txt', 'deep' );
        Helper::recursiveRemove( $stDir . '/a' );
        self::assertFalse( file_exists( $stDir . '/a' ) );
    }


    public function testRecursiveRemoveNonexistentIsNoOp() : void {
        Helper::recursiveRemove( '/nonexistent/path/' . uniqid( more_entropy: true ) );
        /** @phpstan-ignore staticMethod.alreadyNarrowedType */
        self::assertTrue( true );
    }


    public function testResolvePathCollapsesMultipleSlashes() : void {
        self::assertSame( '/a/b/c', Helper::resolvePath( '/a///b//c' ) );
    }


    public function testResolvePathComplexMix() : void {
        self::assertSame( '/x/z', Helper::resolvePath( '/a/b/../../x/./y/../z' ) );
    }


    public function testResolvePathDoubleDotFromRootStaysAtRoot() : void {
        self::assertSame( '/', Helper::resolvePath( '/..' ) );
    }


    public function testResolvePathMultipleDoubleDotsPastRoot() : void {
        self::assertSame( '/', Helper::resolvePath( '/a/../../..' ) );
    }


    public function testResolvePathOnlySlashes() : void {
        self::assertSame( '/', Helper::resolvePath( '///' ) );
    }


    public function testResolvePathRejectsEmptyString() : void {
        self::assertSame( Error::PATH_INVALID, Helper::resolvePath( '' ) );
    }


    public function testResolvePathRejectsRelativePath() : void {
        self::assertSame( Error::PATH_INVALID, Helper::resolvePath( 'a/b/c' ) );
    }


    public function testResolvePathResolvesDot() : void {
        self::assertSame( '/a/b', Helper::resolvePath( '/a/./b' ) );
    }


    public function testResolvePathResolvesDoubleDot() : void {
        self::assertSame( '/a', Helper::resolvePath( '/a/b/..' ) );
    }


    public function testResolvePathResolvesDoubleDotInMiddle() : void {
        self::assertSame( '/a/c', Helper::resolvePath( '/a/b/../c' ) );
    }


    public function testResolvePathRoot() : void {
        self::assertSame( '/', Helper::resolvePath( '/' ) );
    }


    public function testResolvePathSimple() : void {
        self::assertSame( '/a/b/c', Helper::resolvePath( '/a/b/c' ) );
    }


    public function testResolvePathTrailingSlash() : void {
        self::assertSame( '/a/b', Helper::resolvePath( '/a/b/' ) );
    }


    public function testValidatePathCollapsesMultipleSlashes() : void {
        $stDir = $this->tempDirResolved();
        mkdir( $stDir . '/a/b', 0700, true );
        self::assertSame( $stDir . '/a/b', Helper::validatePath( $stDir . '///a//b' ) );
    }


    public function testValidatePathDoubleDotFromRoot() : void {
        self::assertSame( '/', Helper::validatePath( '/..' ) );
    }


    public function testValidatePathExistingDirectory() : void {
        $stDir = $this->tempDirResolved();
        self::assertSame( $stDir, Helper::validatePath( $stDir ) );
    }


    public function testValidatePathExistingFile() : void {
        $stDir = $this->tempDirResolved();
        $stFile = $stDir . '/file.txt';
        file_put_contents( $stFile, 'x' );
        self::assertSame( $stFile, Helper::validatePath( $stFile ) );
    }


    public function testValidatePathNonexistentMustExist() : void {
        $stDir = $this->tempDirResolved();
        self::assertSame( Error::PATH_NOT_FOUND, Helper::validatePath( $stDir . '/nonexistent' ) );
    }


    public function testValidatePathNonexistentNotRequired() : void {
        $stDir = $this->tempDirResolved();
        $stPath = $stDir . '/nonexistent';
        self::assertSame( $stPath, Helper::validatePath( $stPath, false ) );
    }


    public function testValidatePathRejectsRelative() : void {
        self::assertSame( Error::PATH_INVALID, Helper::validatePath( 'a/b/c' ) );
    }


    public function testValidatePathResolvesDot() : void {
        $stDir = $this->tempDirResolved();
        mkdir( $stDir . '/a', 0700 );
        self::assertSame( $stDir . '/a', Helper::validatePath( $stDir . '/./a' ) );
    }


    public function testValidatePathResolvesDoubleDot() : void {
        $stDir = $this->tempDirResolved();
        mkdir( $stDir . '/a/b', 0700, true );
        self::assertSame( $stDir . '/a', Helper::validatePath( $stDir . '/a/b/..' ) );
    }


    public function testValidatePathRoot() : void {
        self::assertSame( '/', Helper::validatePath( '/' ) );
    }


    public function testValidatePathThroughFile() : void {
        $stDir = $this->tempDirResolved();
        $stFile = $stDir . '/file.txt';
        file_put_contents( $stFile, 'x' );
        self::assertSame( Error::PATH_PARENT_NOT_DIRECTORY, Helper::validatePath( $stFile . '/child' ) );
    }


    public function testValidatePathThroughSymlink() : void {
        $stDir = $this->tempDirResolved();
        $stTarget = $stDir . '/target-dir';
        mkdir( $stTarget );
        mkdir( $stTarget . '/child' );
        $stLink = $stDir . '/link';
        symlink( $stTarget, $stLink );
        # Traversing through a symlink returns PATH_PARENT_NOT_DIRECTORY because
        # the symlink component is marked as weird, and subsequent components error.
        self::assertSame( Error::PATH_PARENT_NOT_DIRECTORY, Helper::validatePath( $stLink . '/child' ) );
    }


    public function testValidatePathWithFifo() : void {
        $stDir = $this->tempDirResolved();
        $stFifo = $stDir . '/test.fifo';
        posix_mkfifo( $stFifo, 0600 );
        self::assertSame( Error::PATH_IS_WEIRD, Helper::validatePath( $stFifo ) );
    }


    public function testValidatePathWithSymlinkAtEnd() : void {
        $stDir = $this->tempDirResolved();
        $stTarget = $stDir . '/target.txt';
        file_put_contents( $stTarget, 'x' );
        $stLink = $stDir . '/link';
        symlink( $stTarget, $stLink );
        self::assertSame( Error::PATH_IS_WEIRD, Helper::validatePath( $stLink ) );
    }


    protected function tearDown() : void {
        foreach ( $this->rCleanupPaths as $stPath ) {
            self::recursiveDelete( $stPath );
        }
    }


    private function tempDir() : string {
        $stPath = sys_get_temp_dir() . '/jdwx-helper-test-' . uniqid( more_entropy: true );
        mkdir( $stPath, 0700, true );
        $this->rCleanupPaths[] = $stPath;
        return $stPath;
    }


    /**
     * Create a temp dir with symlinks resolved in the base path.
     * On macOS, sys_get_temp_dir() returns a path through /var which
     * is a symlink to /private/var. validatePath() rejects symlinks,
     * so tests need a base path free of symlinks.
     */
    private function tempDirResolved() : string {
        $stBase = realpath( sys_get_temp_dir() );
        $stPath = $stBase . '/jdwx-helper-test-' . uniqid( more_entropy: true );
        mkdir( $stPath, 0700, true );
        $this->rCleanupPaths[] = $stPath;
        return $stPath;
    }


}
