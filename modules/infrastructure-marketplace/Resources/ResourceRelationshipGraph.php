<?php
declare(strict_types=1);

namespace RATEB\InfrastructureMarketplace\Resources;

/**
 * In-memory relationship graph for infrastructure resources (no provider coupling).
 */
final class ResourceRelationshipGraph
{
    /** @var array<string, array{id:string,type:string,meta:array<string,mixed>}> */
    private array $nodes = [];

    /** @var list<array{from:string,to:string,rel:string}> */
    private array $edges = [];

    /** Directed edges: activation of $to depends on $from being satisfied first. */
    /** @var list<array{from:string,to:string}> */
    private array $activationDependencies = [];

    public function addNode(string $id, string $type, array $meta = []): void
    {
        $this->nodes[$id] = ['id' => $id, 'type' => $type, 'meta' => $meta];
    }

    public function addEdge(string $fromId, string $toId, string $relation = 'related'): void
    {
        if ($fromId === $toId) {
            return;
        }
        if (!isset($this->nodes[$fromId], $this->nodes[$toId])) {
            return;
        }
        $this->edges[] = ['from' => $fromId, 'to' => $toId, 'rel' => $relation];
    }

    /**
     * Activation prerequisite: $to must not activate before $from completes (DAG edges).
     */
    public function addActivationDependency(string $fromId, string $toId): void
    {
        if ($fromId === $toId) {
            return;
        }
        if (!isset($this->nodes[$fromId], $this->nodes[$toId])) {
            return;
        }
        $this->activationDependencies[] = ['from' => $fromId, 'to' => $toId];
    }

    public function hasActivationCycle(): bool
    {
        $adj = [];
        foreach ($this->activationDependencies as $e) {
            $adj[$e['from']][] = $e['to'];
        }
        $visited = [];
        $stack = [];
        foreach (array_keys($this->nodes) as $n) {
            if ($this->dfsCycleActivation($n, $adj, $visited, $stack)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string> activation order; empty if cycle or activation subgraph disconnected from Kahn base
     */
    public function activationTopologicalOrder(): array
    {
        if ($this->activationDependencies === []) {
            return array_keys($this->nodes);
        }
        if ($this->hasActivationCycle()) {
            return [];
        }
        $adj = [];
        $indeg = [];
        foreach (array_keys($this->nodes) as $id) {
            $indeg[$id] = 0;
        }
        foreach ($this->activationDependencies as $e) {
            $adj[$e['from']][] = $e['to'];
            $indeg[$e['to']] = ($indeg[$e['to']] ?? 0) + 1;
        }
        $queue = [];
        foreach ($indeg as $id => $d) {
            if ($d === 0) {
                $queue[] = $id;
            }
        }
        $out = [];
        while ($queue !== []) {
            sort($queue);
            $u = array_shift($queue);
            $out[] = $u;
            foreach ($adj[$u] ?? [] as $v) {
                $indeg[$v]--;
                if ($indeg[$v] === 0) {
                    $queue[] = $v;
                }
            }
        }
        if (count($out) !== count($this->nodes)) {
            return [];
        }

        return $out;
    }

    /**
     * Nodes in activation subgraph with no inbound activation edge (roots of activation DAG).
     *
     * @return list<string>
     */
    public function orphanActivationNodes(): array
    {
        if ($this->activationDependencies === []) {
            return [];
        }
        $targets = [];
        foreach ($this->activationDependencies as $e) {
            $targets[$e['to']] = true;
        }
        $roots = [];
        foreach ($this->activationDependencies as $e) {
            $from = $e['from'];
            if (!isset($targets[$from]) && isset($this->nodes[$from])) {
                $roots[$from] = true;
            }
        }

        return array_keys($roots);
    }

    /**
     * @param list<string> $completedNodeIds
     *
     * @return list<string>
     */
    public function partialActivationDrift(array $completedNodeIds): array
    {
        $done = array_fill_keys($completedNodeIds, true);
        $blocked = [];
        foreach ($this->activationDependencies as $e) {
            if (isset($done[$e['to']]) && !isset($done[$e['from']])) {
                $blocked[] = $e['to'];
            }
        }

        return array_values(array_unique($blocked));
    }

    /**
     * @param array<string, list<string>> $adj
     * @param array<string, bool> $visited
     * @param array<string, bool> $stack
     */
    private function dfsCycleActivation(string $node, array &$adj, array &$visited, array &$stack): bool
    {
        if (!empty($stack[$node])) {
            return true;
        }
        if (!empty($visited[$node])) {
            return false;
        }
        $visited[$node] = true;
        $stack[$node] = true;
        foreach ($adj[$node] ?? [] as $next) {
            if ($this->dfsCycleActivation($next, $adj, $visited, $stack)) {
                return true;
            }
        }
        unset($stack[$node]);

        return false;
    }

    /**
     * @return list<string> node ids with no inbound edges (optional filter by type)
     */
    public function orphanRoots(?string $resourceType = null): array
    {
        $targets = [];
        foreach ($this->edges as $e) {
            $targets[$e['to']] = true;
        }
        $roots = [];
        foreach ($this->nodes as $id => $n) {
            if (isset($targets[$id])) {
                continue;
            }
            if ($resourceType !== null && ($n['type'] ?? '') !== $resourceType) {
                continue;
            }
            $roots[] = $id;
        }

        return $roots;
    }

    /**
     * DFS cycle detection (directed).
     */
    public function hasCycle(): bool
    {
        $adj = [];
        foreach ($this->edges as $e) {
            $adj[$e['from']][] = $e['to'];
        }
        $visited = [];
        $stack = [];
        foreach (array_keys($this->nodes) as $n) {
            if ($this->dfsCycle($n, $adj, $visited, $stack)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, list<string>> $adj
     * @param array<string, bool> $visited
     * @param array<string, bool> $stack
     */
    private function dfsCycle(string $node, array &$adj, array &$visited, array &$stack): bool
    {
        if (!empty($stack[$node])) {
            return true;
        }
        if (!empty($visited[$node])) {
            return false;
        }
        $visited[$node] = true;
        $stack[$node] = true;
        foreach ($adj[$node] ?? [] as $next) {
            if ($this->dfsCycle($next, $adj, $visited, $stack)) {
                return true;
            }
        }
        unset($stack[$node]);

        return false;
    }

    /**
     * @return list<string> BFS path ids
     */
    public function shortestPath(string $fromId, string $toId): array
    {
        if (!isset($this->nodes[$fromId], $this->nodes[$toId])) {
            return [];
        }
        $adj = [];
        foreach ($this->edges as $e) {
            $adj[$e['from']][] = $e['to'];
        }
        $queue = [$fromId];
        $prev = [$fromId => null];
        while ($queue !== []) {
            $cur = array_shift($queue);
            if ($cur === $toId) {
                break;
            }
            foreach ($adj[$cur] ?? [] as $nxt) {
                if (array_key_exists($nxt, $prev)) {
                    continue;
                }
                $prev[$nxt] = $cur;
                $queue[] = $nxt;
            }
        }
        if (!array_key_exists($toId, $prev)) {
            return [];
        }
        $path = [$toId];
        $p = $toId;
        while ($prev[$p] !== null) {
            $p = (string) $prev[$p];
            $path[] = $p;
        }

        return array_reverse($path);
    }
}
