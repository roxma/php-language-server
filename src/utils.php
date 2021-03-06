<?php
declare(strict_types = 1);

namespace LanguageServer;

use Throwable;
use InvalidArgumentException;
use PhpParser\Node;
use Sabre\Event\{Loop, Promise, EmitterInterface};

/**
 * Transforms an absolute file path into a URI as used by the language server protocol.
 *
 * @param string $filepath
 * @return string
 */
function pathToUri(string $filepath): string
{
    $filepath = trim(str_replace('\\', '/', $filepath), '/');
    $parts = explode('/', $filepath);
    // Don't %-encode the colon after a Windows drive letter
    $first = array_shift($parts);
    if (substr($first, -1) !== ':') {
        $first = rawurlencode($first);
    }
    $parts = array_map('rawurlencode', $parts);
    array_unshift($parts, $first);
    $filepath = implode('/', $parts);
    return 'file:///' . $filepath;
}

/**
 * Transforms URI into file path
 *
 * @param string $uri
 * @return string
 */
function uriToPath(string $uri)
{
    $fragments = parse_url($uri);
    if ($fragments === null || !isset($fragments['scheme']) || $fragments['scheme'] !== 'file') {
        throw new InvalidArgumentException("Not a valid file URI: $uri");
    }
    $filepath = urldecode($fragments['path']);
    if (strpos($filepath, ':') !== false) {
        if ($filepath[0] === '/') {
            $filepath = substr($filepath, 1);
        }
        $filepath = str_replace('/', '\\', $filepath);
    }
    return $filepath;
}

/**
 * Throws an exception on the next tick.
 * Useful for letting a promise crash the process on rejection.
 *
 * @param Throwable $err
 * @return void
 */
function crash(Throwable $err)
{
    Loop\nextTick(function () use ($err) {
        throw $err;
    });
}

/**
 * Returns a promise that is resolved after x seconds.
 * Useful for giving back control to the event loop inside a coroutine.
 *
 * @param int $seconds
 * @return Promise <void>
 */
function timeout($seconds = 0): Promise
{
    $promise = new Promise;
    Loop\setTimeout([$promise, 'fulfill'], $seconds);
    return $promise;
}

/**
 * Returns a promise that is fulfilled once the passed event was triggered on the passed EventEmitter
 *
 * @param EmitterInterface $emitter
 * @param string           $event
 * @return Promise
 */
function waitForEvent(EmitterInterface $emitter, string $event): Promise
{
    $p = new Promise;
    $emitter->once($event, [$p, 'fulfill']);
    return $p;
}

/**
 * Returns the closest node of a specific type
 *
 * @param Node $node
 * @param string $type The node class name
 * @return Node|null $type
 */
function getClosestNode(Node $node, string $type)
{
    $n = $node;
    while ($n = $n->getAttribute('parentNode')) {
        if ($n instanceof $type) {
            return $n;
        }
    }
}

/**
 * Returns the part of $b that is not overlapped by $a
 * Example:
 *
 *     stripStringOverlap('whatever<?', '<?php') === 'php'
 *
 * @param string $a
 * @param string $b
 * @return string
 */
function stripStringOverlap(string $a, string $b): string
{
    $aLen = strlen($a);
    $bLen = strlen($b);
    for ($i = 1; $i <= $bLen; $i++) {
        if (substr($b, 0, $i) === substr($a, $aLen - $i)) {
            return substr($b, $i);
        }
    }
    return $b;
}
