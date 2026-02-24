<?php


declare( strict_types = 1 );


namespace JDWX\Volume\Tests;


use JDWX\Strict\OK;
use JDWX\Strict\TypeIs;
use JDWX\Volume\Error;
use JDWX\Volume\Volume;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;


#[CoversClass( Volume::class )]
final class VolumeTest extends TestCase {


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


    public function testConstructorCreatesDirectory() : void {
        $stBase = $this->basePath();
        $vol = new Volume( $stBase, 'new-vol' );
        unset( $vol );
        self::assertDirectoryExists( $stBase . '/new-vol' );
    }


    public function testConstructorDefaultsToTempDir() : void {
        $stIdentifier = 'jdwx-vol-default-base-test-' . uniqid( more_entropy: true );
        $this->rCleanupPaths[] = sys_get_temp_dir() . '/' . $stIdentifier;
        $vol = new Volume( nstIdentifier: $stIdentifier );
        self::assertTrue( $vol->exists() );
        self::assertDirectoryExists( sys_get_temp_dir() . '/' . $stIdentifier );
    }


    public function testConstructorReusesExistingDirectory() : void {
        $stBase = $this->basePath();
        mkdir( $stBase . '/existing', 0700 );
        $vol = new Volume( $stBase, 'existing' );
        self::assertTrue( $vol->exists() );
    }


    public function testConstructorThrowsForNonDirectory() : void {
        $stBase = $this->basePath();
        file_put_contents( $stBase . '/afile', 'not a directory' );
        $this->expectException( \RuntimeException::class );
        $vol = new Volume( $stBase, 'afile' );
        unset( $vol );
    }


    public function testDestroyIsIdempotent() : void {
        $vol = $this->makeVolume();
        $vol->destroy();
        $vol->destroy();
        self::assertFalse( $vol->exists() );
    }


    public function testExists() : void {
        $vol = $this->makeVolume();
        self::assertTrue( $vol->exists() );
    }


    public function testExistsAfterDestroy() : void {
        $vol = $this->makeVolume();
        $vol->destroy();
        self::assertFalse( $vol->exists() );
    }


    public function testListDirectoriesHaveTrailingSlash() : void {
        $vol = $this->makeVolume();
        $vol->writeFile( '/sub/file.txt', 'x' );
        $rList = $vol->list( '/' );
        $rList = TypeIs::array( $rList );
        self::assertContains( '/sub/', $rList );
    }


    public function testListEmptyDirectory() : void {
        $vol = $this->makeVolume();
        $rList = $vol->list( '/' );
        $rList = TypeIs::array( $rList );
        self::assertSame( [], $rList );
    }


    public function testListHiddenFiles() : void {
        $vol = $this->makeVolume();
        self::assertNull( $vol->writeFile( '/visible.txt', 'a' ) );
        self::assertNull( $vol->writeFile( '/.hidden', 'b' ) );
        $rList = $vol->list( '/' );
        $rList = TypeIs::array( $rList );
        self::assertContains( '/visible.txt', $rList );
        self::assertContains( '/.hidden', $rList );
    }


    public function testListHiddenFilesExcludedByWildcard() : void {
        $vol = $this->makeVolume();
        $vol->writeFile( '/visible.txt', 'a' );
        $vol->writeFile( '/.hidden.txt', 'b' );
        $vol->writeFile( '/sub/.secret.txt', 'c' );
        $vol->writeFile( '/sub/visible.txt', 'd' );
        // Simple wildcard: glob() excludes dotfiles. This is standard behavior.
        $rSimple = $vol->list( '/*.txt' );
        $rSimple = TypeIs::array( $rSimple );
        self::assertContains( '/visible.txt', $rSimple );
        self::assertNotContains( '/.hidden.txt', $rSimple );
        // Recursive wildcard: should also exclude dotfiles, consistent with glob().
        $rRecursive = $vol->list( '/**/*.txt' );
        $rRecursive = TypeIs::array( $rRecursive );
        self::assertContains( '/visible.txt', $rRecursive );
        self::assertContains( '/sub/visible.txt', $rRecursive );
        self::assertNotContains( '/.hidden.txt', $rRecursive );
        self::assertNotContains( '/sub/.secret.txt', $rRecursive );
    }


    public function testListNonexistentPath() : void {
        $vol = $this->makeVolume();
        self::assertSame( Error::PATH_NOT_FOUND, $vol->list( '/nope' ) );
    }


    public function testListOnDestroyedVolume() : void {
        $vol = $this->makeVolume();
        $vol->destroy();
        self::assertSame( Error::DIRECTORY_IS_CLOSED, $vol->list( '/' ) );
    }


    public function testListRecursiveWildcard() : void {
        $vol = $this->makeVolume();
        $vol->writeFile( '/top.txt', 'top' );
        $vol->writeFile( '/a/mid.txt', 'mid' );
        $vol->writeFile( '/a/b/deep.txt', 'deep' );
        $vol->writeFile( '/a/b/deep.log', 'log' );
        $rList = $vol->list( '/**/*.txt' );
        $rList = TypeIs::array( $rList );
        self::assertCount( 3, $rList );
        self::assertContains( '/top.txt', $rList );
        self::assertContains( '/a/mid.txt', $rList );
        self::assertContains( '/a/b/deep.txt', $rList );
    }


    public function testListRecursiveWildcardFromSubdirectory() : void {
        $vol = $this->makeVolume();
        $vol->writeFile( '/top.txt', 'top' );
        $vol->writeFile( '/a/mid.txt', 'mid' );
        $vol->writeFile( '/a/b/deep.txt', 'deep' );
        $rList = $vol->list( '/a/**/*.txt' );
        $rList = TypeIs::array( $rList );
        self::assertCount( 2, $rList );
        self::assertContains( '/a/mid.txt', $rList );
        self::assertContains( '/a/b/deep.txt', $rList );
    }


    /** @noinspection PhpMethodNamingConventionInspection */
    public function testListRecursiveWildcardRespectsPreDoubleStarConstraints() : void {
        $vol = $this->makeVolume();
        $vol->writeFile( '/alpha/deep/file.txt', 'match' );
        $vol->writeFile( '/beta/deep/file.txt', 'no match' );
        // Pattern /al*/**/*.txt should only match under directories starting with "al".
        // Current bug: wildcard components before ** are silently discarded,
        // so the search finds all *.txt files under the volume root.
        $rList = $vol->list( '/al*/**/*.txt' );
        $rList = TypeIs::array( $rList );
        self::assertContains( '/alpha/deep/file.txt', $rList );
        self::assertNotContains( '/beta/deep/file.txt', $rList );
    }


    public function testListRecursiveWildcardWithSubdirectoryPattern() : void {
        $vol = $this->makeVolume();
        $vol->writeFile( '/a/sub/file.txt', 'in sub' );
        $vol->writeFile( '/a/other/file.txt', 'in other' );
        // Pattern /**/sub/*.txt should match files inside "sub" directories at any depth.
        // Current bug: recursiveGlob matches against filename only, so
        // multi-component patterns like "sub/*.txt" never match anything.
        $rList = $vol->list( '/**/sub/*.txt' );
        $rList = TypeIs::array( $rList );
        self::assertContains( '/a/sub/file.txt', $rList );
    }


    public function testListSimpleWildcard() : void {
        $vol = $this->makeVolume();
        $vol->writeFile( '/a.txt', 'a' );
        $vol->writeFile( '/b.txt', 'b' );
        $vol->writeFile( '/c.log', 'c' );
        $rList = $vol->list( '/*.txt' );
        $rList = TypeIs::array( $rList );
        self::assertCount( 2, $rList );
        self::assertContains( '/a.txt', $rList );
        self::assertContains( '/b.txt', $rList );
    }


    public function testListSpecificFile() : void {
        $vol = $this->makeVolume();
        $vol->writeFile( '/only.txt', 'x' );
        $rList = $vol->list( '/only.txt' );
        $rList = TypeIs::array( $rList );
        self::assertCount( 1, $rList );
        self::assertSame( '/only.txt', $rList[ 0 ] );
    }


    public function testListSubdirectory() : void {
        $vol = $this->makeVolume();
        $vol->writeFile( '/sub/one.txt', '1' );
        $vol->writeFile( '/sub/two.txt', '2' );
        $rList = $vol->list( '/sub' );
        $rList = TypeIs::array( $rList );
        self::assertCount( 2, $rList );
        self::assertContains( '/sub/one.txt', $rList );
        self::assertContains( '/sub/two.txt', $rList );
    }


    public function testListWildcardDirectoriesHaveTrailingSlash() : void {
        $vol = $this->makeVolume();
        $vol->writeFile( '/sub/file.txt', 'x' );
        $rList = $vol->list( '/*' );
        $rList = TypeIs::array( $rList );
        self::assertContains( '/sub/', $rList );
    }


    public function testListWildcardInSubdirectory() : void {
        $vol = $this->makeVolume();
        $vol->writeFile( '/sub/a.txt', 'a' );
        $vol->writeFile( '/sub/b.log', 'b' );
        $rList = $vol->list( '/sub/*.txt' );
        $rList = TypeIs::array( $rList );
        self::assertCount( 1, $rList );
        self::assertContains( '/sub/a.txt', $rList );
    }


    public function testListWildcardNoMatches() : void {
        $vol = $this->makeVolume();
        $vol->writeFile( '/a.txt', 'a' );
        $rList = $vol->list( '/*.xyz' );
        $rList = TypeIs::array( $rList );
        self::assertSame( [], $rList );
    }


    public function testListWildcardNonexistentBase() : void {
        $vol = $this->makeVolume();
        self::assertSame( Error::PATH_NOT_FOUND, $vol->list( '/nonexistent/*.txt' ) );
    }


    public function testListWildcardOnDestroyedVolume() : void {
        $vol = $this->makeVolume();
        $vol->destroy();
        self::assertSame( Error::DIRECTORY_IS_CLOSED, $vol->list( '/*.txt' ) );
    }


    public function testListWildcardRejectsShellMetacharacters() : void {
        $vol = $this->makeVolume();
        self::assertSame( Error::PATH_INVALID, $vol->list( '/$(cmd)' ) );
    }


    public function testListWithFiles() : void {
        $vol = $this->makeVolume();
        $vol->writeFile( '/a.txt', 'a' );
        $vol->writeFile( '/b.txt', 'b' );
        $rList = $vol->list( '/' );
        $rList = TypeIs::array( $rList );
        self::assertCount( 2, $rList );
        self::assertContains( '/a.txt', $rList );
        self::assertContains( '/b.txt', $rList );
    }


    public function testMultipleSlashesAreNormalized() : void {
        $vol = $this->makeVolume();
        $vol->writeFile( '/file.txt', 'slashes' );
        self::assertSame( 'slashes', $vol->readFile( '///file.txt' ) );
    }


    public function testNormalizePath() : void {
        $vol = $this->makeVolume();
        $class = new \ReflectionClass( $vol );
        $fnNormalizePath = $class->getMethod( 'normalizePath' );
        $stPath = $class->getProperty( 'stPath' )->getValue( $vol );
        self::assertSame( $stPath, $fnNormalizePath->invoke( $vol, '/' ) );
        self::assertSame( "{$stPath}/foo/bar", $fnNormalizePath->invoke( $vol, '/foo/bar' ) );
        self::assertSame( "{$stPath}/foo/bar", $fnNormalizePath->invoke( $vol, '/foo/bar/' ) );
        self::assertSame( "{$stPath}/foo.txt", $fnNormalizePath->invoke( $vol, '/////foo.txt' ) );
    }


    public function testNormalizePathExists() : void {
        $vol = $this->makeVolume();
        $class = new \ReflectionClass( $vol );
        $fnNormalizePathExisting = $class->getMethod( 'normalizePathExists' );
        $stPath = $class->getProperty( 'stPath' )->getValue( $vol );
        self::assertSame( $stPath, $fnNormalizePathExisting->invoke( $vol, '/' ) );
        self::assertSame( Error::PATH_NOT_FOUND, $fnNormalizePathExisting->invoke( $vol, '/nonexistent' ) );
    }


    public function testNormalizePathParentExists() : void {
        $vol = $this->makeVolume();
        $class = new \ReflectionClass( $vol );
        $fnNormalizePathParentExists = $class->getMethod( 'normalizePathParentExists' );
        $stPath = $class->getProperty( 'stPath' )->getValue( $vol );
        self::assertSame( $stPath, $fnNormalizePathParentExists->invoke( $vol, '/', false ) );
        self::assertSame( $stPath, $fnNormalizePathParentExists->invoke( $vol, '/foo', false ) );
        self::assertSame( $stPath, $fnNormalizePathParentExists->invoke( $vol, '/foo/', false ) );
        self::assertSame( Error::PATH_PARENT_NOT_FOUND, $fnNormalizePathParentExists->invoke( $vol, '/foo/bar', false ) );
        self::assertSame( "{$stPath}/foo", $fnNormalizePathParentExists->invoke( $vol, '/foo/bar', true ) );
        self::assertDirectoryExists( "{$stPath}/foo" );
        self::assertSame( "{$stPath}/baz/qux", $fnNormalizePathParentExists->invoke( $vol, '/baz/qux/quux', true ) );
        self::assertDirectoryExists( "{$stPath}/baz/qux" );
    }


    public function testNormalizePathParentForAboveRoot() : void {
        $vol = $this->makeVolume();
        $class = new \ReflectionClass( $vol );
        $fnNormalizePathParent = $class->getMethod( 'normalizePathParent' );
        $stPath = $class->getProperty( 'stPath' )->getValue( $vol );
        self::assertSame( $stPath, $fnNormalizePathParent->invoke( $vol, '/foo/../../bar', false ) );
    }


    public function testNormalizePathParentForRoot() : void {
        $vol = $this->makeVolume();
        $class = new \ReflectionClass( $vol );
        $fnNormalizePathParent = $class->getMethod( 'normalizePathParent' );
        $stPath = $class->getProperty( 'stPath' )->getValue( $vol );
        self::assertSame( $stPath, $fnNormalizePathParent->invoke( $vol, '/', false ) );
        self::assertSame( $stPath, $fnNormalizePathParent->invoke( $vol, '/foo', false ) );
        self::assertSame( $stPath, $fnNormalizePathParent->invoke( $vol, '/foo/', false ) );
    }


    public function testNormalizePathParentForSubdirectory() : void {
        $vol = $this->makeVolume();
        $class = new \ReflectionClass( $vol );
        $fnNormalizePathParent = $class->getMethod( 'normalizePathParent' );
        $stPath = $class->getProperty( 'stPath' )->getValue( $vol );
        $vol->writeFile( '/foo/baz', 'x' );
        self::assertSame( "{$stPath}/foo", $fnNormalizePathParent->invoke( $vol, '/foo/bar', false ) );
        self::assertSame( "{$stPath}/foo", $fnNormalizePathParent->invoke( $vol, '/foo/bar/', false ) );
    }


    public function testPathCannotEscapeVolumeRoot() : void {
        $vol = $this->makeVolume();
        // Attempting "/../../../etc/passwd" should resolve to /etc/passwd
        // inside the volume (which doesn't exist), not the real /etc/passwd.
        $result = $vol->readFile( '/../../../etc/passwd' );
        self::assertInstanceOf( Error::class, $result );
    }


    public function testPathExistsForDirectory() : void {
        $vol = $this->makeVolume();
        $vol->writeFile( '/dir/file.txt', 'x' );
        self::assertTrue( $vol->pathExists( '/dir' ) );
    }


    public function testPathExistsForFile() : void {
        $vol = $this->makeVolume();
        $vol->writeFile( '/file.txt', 'x' );
        self::assertTrue( $vol->pathExists( '/file.txt' ) );
    }


    public function testPathExistsForRoot() : void {
        $vol = $this->makeVolume();
        self::assertTrue( $vol->pathExists( '/' ) );
    }


    public function testPathExistsNonexistent() : void {
        $vol = $this->makeVolume();
        self::assertFalse( $vol->pathExists( '/nothing' ) );
    }


    public function testPathExistsOnDestroyedVolume() : void {
        $vol = $this->makeVolume();
        $vol->destroy();
        self::assertFalse( $vol->pathExists( '/' ) );
    }


    public function testPathWithDotIsResolved() : void {
        $vol = $this->makeVolume();
        $vol->writeFile( '/file.txt', 'dot' );
        self::assertSame( 'dot', $vol->readFile( '/./file.txt' ) );
    }


    public function testPathWithDoubleDotIsResolved() : void {
        $vol = $this->makeVolume();
        $vol->writeFile( '/a/b.txt', 'dotdot' );
        self::assertSame( 'dotdot', $vol->readFile( '/a/c/../b.txt' ) );
    }


    public function testPathWithHyphenIsAllowed() : void {
        $vol = $this->makeVolume();
        $err = $vol->writeFile( '/my-file.txt', 'hyphen' );
        self::assertNull( $err );
        self::assertSame( 'hyphen', $vol->readFile( '/my-file.txt' ) );
    }


    public function testPathWithUnderscoreIsAllowed() : void {
        $vol = $this->makeVolume();
        $err = $vol->writeFile( '/my_file.txt', 'underscore' );
        self::assertNull( $err );
        self::assertSame( 'underscore', $vol->readFile( '/my_file.txt' ) );
    }


    public function testPersistentVolumeNotAutoDestroyed() : void {
        $stBase = $this->basePath();
        $vol = new Volume( $stBase, 'persist' );
        unset( $vol );
        self::assertDirectoryExists( $stBase . '/persist' );
    }


    public function testPersistentVolumeRetainsDataAcrossInstances() : void {
        $stBase = $this->basePath();
        $vol1 = new Volume( $stBase, 'persist' );
        $vol1->writeFile( '/data.txt', 'hello' );
        unset( $vol1 );
        $vol2 = new Volume( $stBase, 'persist' );
        self::assertSame( 'hello', $vol2->readFile( '/data.txt' ) );
    }


    public function testReadFile() : void {
        $vol = $this->makeVolume();
        $vol->writeFile( '/read.txt', 'read-me' );
        self::assertSame( 'read-me', $vol->readFile( '/read.txt' ) );
    }


    public function testReadFileNonexistent() : void {
        $vol = $this->makeVolume();
        self::assertSame( Error::PATH_NOT_FOUND, $vol->readFile( '/nope.txt' ) );
    }


    public function testReadFileOnDestroyedVolume() : void {
        $vol = $this->makeVolume();
        $vol->destroy();
        self::assertSame( Error::DIRECTORY_IS_CLOSED, $vol->readFile( '/file.txt' ) );
    }


    public function testReadFileReturnsErrorForDirectory() : void {
        $vol = $this->makeVolume();
        $vol->writeFile( '/dir/file.txt', 'x' );
        self::assertSame( Error::PATH_IS_DIRECTORY, $vol->readFile( '/dir' ) );
    }


    public function testReadFileWithMaxLength() : void {
        $vol = $this->makeVolume();
        $vol->writeFile( '/long.txt', 'abcdefghij' );
        self::assertSame( 'abcde', $vol->readFile( '/long.txt', 5 ) );
    }


    public function testRemoveDirectoryRecursively() : void {
        $vol = $this->makeVolume();
        $vol->writeFile( '/dir/a.txt', 'a' );
        $vol->writeFile( '/dir/b.txt', 'b' );
        self::assertNull( $vol->remove( '/dir' ) );
        self::assertFalse( $vol->pathExists( '/dir' ) );
    }


    public function testRemoveFile() : void {
        $vol = $this->makeVolume();
        $vol->writeFile( '/doomed.txt', 'bye' );
        self::assertNull( $vol->remove( '/doomed.txt' ) );
        self::assertFalse( $vol->pathExists( '/doomed.txt' ) );
    }


    public function testRemoveNonexistent() : void {
        $vol = $this->makeVolume();
        self::assertSame( Error::PATH_NOT_FOUND, $vol->remove( '/ghost' ) );
    }


    public function testRemoveOnDestroyedVolume() : void {
        $vol = $this->makeVolume();
        $vol->destroy();
        self::assertSame( Error::DIRECTORY_IS_CLOSED, $vol->remove( '/x' ) );
    }


    public function testRemoveRootClearsContents() : void {
        $vol = $this->makeVolume();
        $vol->writeFile( '/x.txt', 'x' );
        $vol->writeFile( '/y.txt', 'y' );
        self::assertNull( $vol->remove( '/' ) );
        self::assertTrue( $vol->exists() );
        $rList = $vol->list( '/' );
        $rList = TypeIs::array( $rList );
        self::assertSame( [], $rList );
    }


    public function testRenameCreatesParentDirectories() : void {
        $vol = $this->makeVolume();
        $vol->writeFile( '/file.txt', 'content' );
        self::assertNull( $vol->rename( '/file.txt', '/new/dir/file.txt' ) );
        self::assertSame( 'content', $vol->readFile( '/new/dir/file.txt' ) );
    }


    public function testRenameFile() : void {
        $vol = $this->makeVolume();
        $vol->writeFile( '/old.txt', 'content' );
        self::assertNull( $vol->rename( '/old.txt', '/new.txt' ) );
        self::assertFalse( $vol->pathExists( '/old.txt' ) );
        self::assertSame( 'content', $vol->readFile( '/new.txt' ) );
    }


    public function testRenameIntoDirectory() : void {
        $vol = $this->makeVolume();
        $vol->writeFile( '/file.txt', 'moved' );
        $vol->writeFile( '/dest/placeholder.txt', 'x' );
        self::assertNull( $vol->rename( '/file.txt', '/dest' ) );
        self::assertSame( 'moved', $vol->readFile( '/dest/file.txt' ) );
    }


    public function testRenameNonexistentSource() : void {
        $vol = $this->makeVolume();
        self::assertSame( Error::PATH_NOT_FOUND, $vol->rename( '/ghost.txt', '/new.txt' ) );
    }


    public function testRenameOnDestroyedVolume() : void {
        $vol = $this->makeVolume();
        $vol->destroy();
        self::assertSame( Error::DIRECTORY_IS_CLOSED, $vol->rename( '/a', '/b' ) );
    }


    public function testRenameReturnsErrorForExistingTarget() : void {
        $vol = $this->makeVolume();
        $vol->writeFile( '/a.txt', 'a' );
        $vol->writeFile( '/b.txt', 'b' );
        self::assertSame( Error::PATH_EXISTS, $vol->rename( '/a.txt', '/b.txt' ) );
    }


    public function testReplaceFileCreatesNew() : void {
        $vol = $this->makeVolume();
        $err = $vol->replaceFile( '/new.txt', 'content' );
        self::assertNull( $err );
        self::assertSame( 'content', $vol->readFile( '/new.txt' ) );
    }


    public function testReplaceFileCreatesParentDirectories() : void {
        $vol = $this->makeVolume();
        $err = $vol->replaceFile( '/p/q/r.txt', 'nested' );
        self::assertNull( $err );
        self::assertSame( 'nested', $vol->readFile( '/p/q/r.txt' ) );
    }


    public function testReplaceFileOnDestroyedVolume() : void {
        $vol = $this->makeVolume();
        $vol->destroy();
        self::assertSame( Error::DIRECTORY_IS_CLOSED, $vol->replaceFile( '/file.txt', 'data' ) );
    }


    public function testReplaceFileOverwritesExisting() : void {
        $vol = $this->makeVolume();
        $vol->writeFile( '/replace.txt', 'original' );
        $err = $vol->replaceFile( '/replace.txt', 'updated' );
        self::assertNull( $err );
        self::assertSame( 'updated', $vol->readFile( '/replace.txt' ) );
    }


    public function testReplaceFileReturnsErrorForDirectory() : void {
        $vol = $this->makeVolume();
        $vol->writeFile( '/dir/child.txt', 'x' );
        self::assertSame( Error::PATH_IS_DIRECTORY, $vol->replaceFile( '/dir', 'nope' ) );
    }


    public function testTemporaryVolumeAutoDestroys() : void {
        $stBase = $this->basePath();
        $rBefore = OK::glob( $stBase . '/*' );
        $vol = new Volume( $stBase );
        $rDuring = OK::glob( $stBase . '/*' );
        self::assertCount( count( $rBefore ) + 1, $rDuring );
        unset( $vol );
        $rAfter = OK::glob( $stBase . '/*' );
        self::assertCount( count( $rBefore ), $rAfter );
    }


    public function testWriteFile() : void {
        $vol = $this->makeVolume();
        $err = $vol->writeFile( '/hello.txt', 'world' );
        self::assertNull( $err );
        self::assertSame( 'world', $vol->readFile( '/hello.txt' ) );
    }


    public function testWriteFileBinaryContent() : void {
        $vol = $this->makeVolume();
        $stBinary = "\x00\x01\x02\xff";
        $err = $vol->writeFile( '/bin.dat', $stBinary );
        self::assertNull( $err );
        self::assertSame( $stBinary, $vol->readFile( '/bin.dat' ) );
    }


    public function testWriteFileCreatesParentDirectories() : void {
        $vol = $this->makeVolume();
        $err = $vol->writeFile( '/a/b/c.txt', 'deep' );
        self::assertNull( $err );
        self::assertSame( 'deep', $vol->readFile( '/a/b/c.txt' ) );
    }


    public function testWriteFileDoubleDotCannotEscapeVolume() : void {
        $vol = $this->makeVolume();
        $stVolumePath = $vol->path();
        $stParentDir = dirname( $stVolumePath );
        $stEscapedPath = $stParentDir . '/escape.txt';

        // Create a directory so the .. traversal could resolve through it.
        $vol->writeFile( '/existing/placeholder.txt', 'x' );

        // /existing/../../escape.txt should resolve to /escape.txt inside the volume
        // (since .. from root goes back to root), NOT write outside the sandbox.
        $result = $vol->writeFile( '/existing/../../escape.txt', 'escaped' );

        // The file must not exist outside the volume.
        self::assertFileDoesNotExist( $stEscapedPath, 'File was written outside the volume sandbox' );

        // The write should succeed, landing at /escape.txt inside the volume.
        self::assertNull( $result );
        self::assertSame( 'escaped', $vol->readFile( '/escape.txt' ) );
    }


    public function testWriteFileEmptyContent() : void {
        $vol = $this->makeVolume();
        $err = $vol->writeFile( '/empty.txt', '' );
        self::assertNull( $err );
        self::assertSame( '', $vol->readFile( '/empty.txt' ) );
    }


    public function testWriteFileOnDestroyedVolume() : void {
        $vol = $this->makeVolume();
        $vol->destroy();
        self::assertSame( Error::DIRECTORY_IS_CLOSED, $vol->writeFile( '/file.txt', 'data' ) );
    }


    public function testWriteFileReturnsErrorForExistingPath() : void {
        $vol = $this->makeVolume();
        $vol->writeFile( '/exists.txt', 'first' );
        $err = $vol->writeFile( '/exists.txt', 'second' );
        self::assertSame( Error::PATH_EXISTS, $err );
        self::assertSame( 'first', $vol->readFile( '/exists.txt' ) );
    }


    protected function tearDown() : void {
        foreach ( $this->rCleanupPaths as $stPath ) {
            self::recursiveDelete( $stPath );
        }
    }


    private function basePath() : string {
        $stBase = sys_get_temp_dir() . '/jdwx-volume-test-' . uniqid( more_entropy: true );
        mkdir( $stBase, 0700, true );
        $this->rCleanupPaths[] = $stBase;
        return $stBase;
    }


    /** Create a persistent volume with a known identifier for testing. */
    private function makeVolume() : Volume {
        return new Volume( $this->basePath(), 'test-vol' );
    }


}
