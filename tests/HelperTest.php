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


    public function testFileFromPath() : void {
        self::assertSame( 'file.txt', Helper::fileFromPath( '/a/b/file.txt' ) );
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


    public function testGlobWildNonexistent() : void {
        self::assertSame( Error::PATH_NOT_FOUND, Helper::globWild( '/nonexistent/path' ) );
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


}
