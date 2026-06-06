<?php

declare(strict_types=1);

namespace QuantumApp\Model;

/**
 * Repository for persisting and retrieving quantum circuits as JSON.
 * Handles all storage I/O, keeping persistence logic out of controllers.
 */
class CircuitRepository
{
    private string $storageFile;

    public function __construct(string $storageFile)
    {
        $this->storageFile = $storageFile;
    }

    /**
     * List all saved circuits (returns name and numQubits metadata only).
     * @return array<int, array{name: string, numQubits: int}>
     */
    public function listAll(): array
    {
        $saved = $this->loadAll();
        return array_map(function ($item) {
            return [
                'name' => $item['name'],
                'numQubits' => $item['circuit']['numQubits'] ?? 2
            ];
        }, $saved);
    }

    /**
     * Load a single circuit by name (case-insensitive).
     * Returns the raw circuit data array or null if not found.
     */
    public function findByName(string $name): ?array
    {
        foreach ($this->loadAll() as $item) {
            if (strcasecmp($item['name'], $name) === 0) {
                return $item['circuit'];
            }
        }
        return null;
    }

    /**
     * Save a circuit by name. If a circuit with the same name already exists,
     * it will be overwritten (upsert behaviour).
     */
    public function save(string $name, array $circuitData): void
    {
        $saved = $this->loadAll();

        $updated = false;
        foreach ($saved as &$item) {
            if (strcasecmp($item['name'], $name) === 0) {
                $item['circuit'] = $circuitData;
                $updated = true;
                break;
            }
        }
        unset($item);

        if (!$updated) {
            $saved[] = [
                'name' => $name,
                'circuit' => $circuitData
            ];
        }

        $this->persist($saved);
    }

    /**
     * Delete a circuit by name (case-insensitive). No-op if not found.
     */
    public function deleteByName(string $name): void
    {
        $saved = $this->loadAll();
        $filtered = array_values(array_filter($saved, function ($item) use ($name) {
            return strcasecmp($item['name'], $name) !== 0;
        }));
        $this->persist($filtered);
    }

    /**
     * Load all circuits from the storage file.
     * @return array<int, array>
     */
    private function loadAll(): array
    {
        if (!file_exists($this->storageFile)) {
            return [];
        }
        $contents = file_get_contents($this->storageFile);
        if ($contents === false) {
            return [];
        }
        return json_decode($contents, true) ?: [];
    }

    /**
     * Write all circuits to the storage file.
     */
    private function persist(array $data): void
    {
        $result = file_put_contents($this->storageFile, json_encode($data, JSON_PRETTY_PRINT));
        if ($result === false) {
            throw new \RuntimeException('Failed to write circuit to storage. Check folder permissions.');
        }
    }
}
