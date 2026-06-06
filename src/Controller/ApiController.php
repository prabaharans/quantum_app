<?php

declare(strict_types=1);

namespace QuantumApp\Controller;

use QuantumApp\Model\CircuitRepository;
use QuantumApp\Model\QuantumCircuit;
use QuantumApp\Model\QuantumSimulator;

/**
 * ApiController — handles all JSON API endpoints for the Quantum Simulator.
 *
 * Routes handled (via Router):
 *  GET  /api/presets
 *  GET  /api/circuits
 *  GET  /api/circuits/load?name=...
 *  POST /api/simulate
 *  POST /api/circuits/save
 *  POST /api/circuits/delete
 */
class ApiController
{
    private CircuitRepository $repository;

    /** Built-in quantum circuit presets */
    private array $presets = [
        [
            'id' => 'bell_state',
            'name' => 'Bell State (Entanglement)',
            'description' => 'Creates the maximally entangled EPR pair (|00⟩ + |11⟩) / √2. Measuring one qubit instantly determines the state of the other.',
            'numQubits' => 2,
            'gates' => [
                ['type' => 'h',       'target' => 0, 'controls' => null, 'angle' => null, 'step' => 0],
                ['type' => 'cx',      'target' => 1, 'controls' => [0],  'angle' => null, 'step' => 1],
                ['type' => 'measure', 'target' => 0, 'controls' => null, 'angle' => null, 'step' => 2],
                ['type' => 'measure', 'target' => 1, 'controls' => null, 'angle' => null, 'step' => 2],
            ]
        ],
        [
            'id' => 'ghz_state',
            'name' => 'GHZ State (3-Qubit Entanglement)',
            'description' => 'Creates a Greenberger-Horne-Zeilinger state (|000⟩ + |111⟩) / √2. A classic example of multi-partite entanglement.',
            'numQubits' => 3,
            'gates' => [
                ['type' => 'h',       'target' => 0, 'controls' => null, 'angle' => null, 'step' => 0],
                ['type' => 'cx',      'target' => 1, 'controls' => [0],  'angle' => null, 'step' => 1],
                ['type' => 'cx',      'target' => 2, 'controls' => [1],  'angle' => null, 'step' => 2],
                ['type' => 'measure', 'target' => 0, 'controls' => null, 'angle' => null, 'step' => 3],
                ['type' => 'measure', 'target' => 1, 'controls' => null, 'angle' => null, 'step' => 3],
                ['type' => 'measure', 'target' => 2, 'controls' => null, 'angle' => null, 'step' => 3],
            ]
        ],
        [
            'id' => 'teleportation',
            'name' => 'Quantum Teleportation',
            'description' => 'Teleports a quantum state from Alice\'s qubit (q0) to Bob\'s qubit (q2) using an entangled pair (q1, q2) and classical conditional gates.',
            'numQubits' => 3,
            'gates' => [
                ['type' => 'ry',      'target' => 0, 'controls' => null, 'angle' => 1.0471975511965976, 'step' => 0],
                ['type' => 'h',       'target' => 1, 'controls' => null, 'angle' => null, 'step' => 1],
                ['type' => 'cx',      'target' => 2, 'controls' => [1],  'angle' => null, 'step' => 2],
                ['type' => 'cx',      'target' => 1, 'controls' => [0],  'angle' => null, 'step' => 3],
                ['type' => 'h',       'target' => 0, 'controls' => null, 'angle' => null, 'step' => 4],
                ['type' => 'measure', 'target' => 0, 'controls' => null, 'angle' => null, 'step' => 5],
                ['type' => 'measure', 'target' => 1, 'controls' => null, 'angle' => null, 'step' => 5],
                ['type' => 'x',       'target' => 2, 'controls' => null, 'angle' => null, 'step' => 6, 'conditional' => ['qubit' => 1, 'value' => 1]],
                ['type' => 'z',       'target' => 2, 'controls' => null, 'angle' => null, 'step' => 7, 'conditional' => ['qubit' => 0, 'value' => 1]],
                ['type' => 'measure', 'target' => 2, 'controls' => null, 'angle' => null, 'step' => 8],
            ]
        ],
        [
            'id' => 'deutsch_jozsa',
            'name' => 'Deutsch-Jozsa (Balanced Function)',
            'description' => 'Determines if a function f(x) is constant or balanced in a single query. Using a balanced oracle (f(x) = x), the query qubit (q0) is measured as |1⟩ with 100% probability.',
            'numQubits' => 2,
            'gates' => [
                ['type' => 'x',       'target' => 1, 'controls' => null, 'angle' => null, 'step' => 0],
                ['type' => 'h',       'target' => 0, 'controls' => null, 'angle' => null, 'step' => 1],
                ['type' => 'h',       'target' => 1, 'controls' => null, 'angle' => null, 'step' => 1],
                ['type' => 'cx',      'target' => 1, 'controls' => [0],  'angle' => null, 'step' => 2],
                ['type' => 'h',       'target' => 0, 'controls' => null, 'angle' => null, 'step' => 3],
                ['type' => 'measure', 'target' => 0, 'controls' => null, 'angle' => null, 'step' => 4],
            ]
        ],
        [
            'id' => 'grover_2qubit',
            'name' => "Grover's Search (2-Qubit, target |11⟩)",
            'description' => "Grover's quantum search algorithm that finds |11⟩ in 1 iteration with high probability. Demonstrates quadratic speedup over classical search.",
            'numQubits' => 2,
            'gates' => [
                // Initialize superposition
                ['type' => 'h', 'target' => 0, 'controls' => null, 'angle' => null, 'step' => 0],
                ['type' => 'h', 'target' => 1, 'controls' => null, 'angle' => null, 'step' => 0],
                // Oracle: mark |11> by flipping phase (CZ gate acts as -I on |11>)
                ['type' => 'cz', 'target' => 1, 'controls' => [0], 'angle' => null, 'step' => 1],
                // Diffusion operator: H, X, CZ, X, H
                ['type' => 'h', 'target' => 0, 'controls' => null, 'angle' => null, 'step' => 2],
                ['type' => 'h', 'target' => 1, 'controls' => null, 'angle' => null, 'step' => 2],
                ['type' => 'x', 'target' => 0, 'controls' => null, 'angle' => null, 'step' => 3],
                ['type' => 'x', 'target' => 1, 'controls' => null, 'angle' => null, 'step' => 3],
                ['type' => 'cz', 'target' => 1, 'controls' => [0], 'angle' => null, 'step' => 4],
                ['type' => 'x', 'target' => 0, 'controls' => null, 'angle' => null, 'step' => 5],
                ['type' => 'x', 'target' => 1, 'controls' => null, 'angle' => null, 'step' => 5],
                ['type' => 'h', 'target' => 0, 'controls' => null, 'angle' => null, 'step' => 6],
                ['type' => 'h', 'target' => 1, 'controls' => null, 'angle' => null, 'step' => 6],
                // Measure
                ['type' => 'measure', 'target' => 0, 'controls' => null, 'angle' => null, 'step' => 7],
                ['type' => 'measure', 'target' => 1, 'controls' => null, 'angle' => null, 'step' => 7],
            ]
        ],
        [
            'id' => 'qft_3qubit',
            'name' => 'Quantum Fourier Transform (3-Qubit)',
            'description' => 'Applies the 3-qubit QFT — the quantum analogue of the DFT, used as a subroutine in Shor\'s algorithm and quantum phase estimation. Demonstrates exponential parallelism.',
            'numQubits' => 3,
            'gates' => [
                // Input: |001> (q0=1)
                ['type' => 'x', 'target' => 0, 'controls' => null, 'angle' => null, 'step' => 0],
                // QFT on q2 (MSB)
                ['type' => 'h', 'target' => 2, 'controls' => null, 'angle' => null, 'step' => 1],
                ['type' => 'p', 'target' => 2, 'controls' => [1],  'angle' => M_PI / 2.0, 'step' => 2],
                ['type' => 'p', 'target' => 2, 'controls' => [0],  'angle' => M_PI / 4.0, 'step' => 3],
                // QFT on q1
                ['type' => 'h', 'target' => 1, 'controls' => null, 'angle' => null, 'step' => 4],
                ['type' => 'p', 'target' => 1, 'controls' => [0],  'angle' => M_PI / 2.0, 'step' => 5],
                // QFT on q0 (LSB)
                ['type' => 'h', 'target' => 0, 'controls' => null, 'angle' => null, 'step' => 6],
                // Bit reversal (SWAP)
                ['type' => 'swap', 'target' => 0, 'controls' => null, 'angle' => null, 'step' => 7, 'target2' => 2],
            ]
        ],
        [
            'id' => 'w_state',
            'name' => 'W-State (3-Qubit)',
            'description' => 'Creates the W-state (|100⟩ + |010⟩ + |001⟩)/√3 — an entangled state where exactly one qubit is in |1⟩. Robust to qubit loss unlike GHZ states.',
            'numQubits' => 3,
            'gates' => [
                ['type' => 'x',  'target' => 0, 'controls' => null, 'angle' => null,                     'step' => 0],
                ['type' => 'ry', 'target' => 1, 'controls' => [0],  'angle' => 2.0 * acos(sqrt(2.0/3.0)),'step' => 1],
                ['type' => 'cx', 'target' => 0, 'controls' => [1],  'angle' => null,                     'step' => 2],
                ['type' => 'ry', 'target' => 2, 'controls' => [1],  'angle' => M_PI / 2.0,               'step' => 3],
                ['type' => 'cx', 'target' => 1, 'controls' => [2],  'angle' => null,                     'step' => 4],
                ['type' => 'measure', 'target' => 0, 'controls' => null, 'angle' => null, 'step' => 5],
                ['type' => 'measure', 'target' => 1, 'controls' => null, 'angle' => null, 'step' => 5],
                ['type' => 'measure', 'target' => 2, 'controls' => null, 'angle' => null, 'step' => 5],
            ]
        ],
        [
            'id' => 'toffoli_demo',
            'name' => 'Toffoli Gate (CCX / AND)',
            'description' => 'Demonstrates the 3-qubit Toffoli (CCX) gate — a universal reversible gate. q2 is flipped only when both q0 and q1 are |1⟩, implementing quantum AND.',
            'numQubits' => 3,
            'gates' => [
                ['type' => 'x',       'target' => 0, 'controls' => null,    'angle' => null, 'step' => 0],
                ['type' => 'x',       'target' => 1, 'controls' => null,    'angle' => null, 'step' => 0],
                ['type' => 'ccx',     'target' => 2, 'controls' => [0, 1],  'angle' => null, 'step' => 1],
                ['type' => 'measure', 'target' => 2, 'controls' => null,    'angle' => null, 'step' => 2],
            ]
        ]
    ];

    public function __construct(CircuitRepository $repository)
    {
        $this->repository = $repository;
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function jsonResponse(array $data, int $code = 200): void
    {
        http_response_code($code);
        echo json_encode($data);
    }

    private function errorResponse(string $message, int $code = 400): void
    {
        $this->jsonResponse(['status' => 'error', 'message' => $message], $code);
    }

    private function getRequestBody(): array
    {
        $body = file_get_contents('php://input');
        if (empty($body)) {
            throw new \InvalidArgumentException('Empty request body.');
        }
        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw new \InvalidArgumentException('Invalid JSON request body.');
        }
        return $data;
    }

    // ─── GET endpoints ────────────────────────────────────────────────────────

    /**
     * GET /api/presets — return built-in circuit presets.
     */
    public function presets(): void
    {
        $this->jsonResponse(['status' => 'success', 'presets' => $this->presets]);
    }

    /**
     * GET /api/circuits — list all saved circuits (name + numQubits).
     */
    public function listCircuits(): void
    {
        $this->jsonResponse(['status' => 'success', 'circuits' => $this->repository->listAll()]);
    }

    /**
     * GET /api/circuits/load?name=... — load a single saved circuit by name.
     */
    public function loadCircuit(): void
    {
        $name = trim($_GET['name'] ?? '');
        if ($name === '') {
            $this->errorResponse('Missing name parameter.');
            return;
        }

        $circuitData = $this->repository->findByName($name);
        if ($circuitData === null) {
            $this->errorResponse("Circuit '{$name}' not found.", 404);
            return;
        }

        $this->jsonResponse(['status' => 'success', 'circuit' => $circuitData]);
    }

    // ─── POST endpoints ───────────────────────────────────────────────────────

    /**
     * POST /api/simulate — run a simulation of the submitted circuit JSON.
     */
    public function simulate(): void
    {
        $data = $this->getRequestBody();

        $circuitData = $data['circuit'] ?? null;
        if (!$circuitData) {
            $this->errorResponse('Missing circuit data.');
            return;
        }

        $circuit = QuantumCircuit::fromArray($circuitData);
        $simulator = new QuantumSimulator();

        // Run step-by-step for state visualization
        $stepByStepResult = $simulator->runStepByStep($circuit);

        $stepByStepJson = [];
        foreach ($stepByStepResult as $stepIdx => $stepInfo) {
            $sv = $stepInfo['statevector'];
            $amplitudes = [];
            $numStates = 1 << $sv->numQubits;

            for ($i = 0; $i < $numStates; $i++) {
                $real   = $sv->amplitudes[2 * $i];
                $imag   = $sv->amplitudes[2 * $i + 1];
                $binary = str_pad(decbin($i), $sv->numQubits, '0', STR_PAD_LEFT);
                $amplitudes[] = [
                    'state' => $binary,
                    'real'  => $real,
                    'imag'  => $imag,
                    'prob'  => $real * $real + $imag * $imag,
                    'phase' => atan2($imag, $real)
                ];
            }

            $stepByStepJson[$stepIdx] = [
                'amplitudes'   => $amplitudes,
                'dirac'        => $stepInfo['dirac'],
                'blochSpheres' => $stepInfo['blochSpheres'],
                'entropy'      => $stepInfo['entropy'] ?? []
            ];
        }

        // Run 1024 measurement shots
        $shotsCounts = $simulator->runShots($circuit, 1024);

        // Export PHP code and QASM
        $phpCode  = $circuit->exportToPhpCode();
        $qasmCode = $circuit->exportToQASM();

        $this->jsonResponse([
            'status'     => 'success',
            'stepByStep' => $stepByStepJson,
            'shots'      => $shotsCounts,
            'phpCode'    => $phpCode,
            'qasmCode'   => $qasmCode
        ]);
    }

    /**
     * POST /api/export/qasm — export a circuit to OpenQASM 2.0.
     */
    public function exportQasm(): void
    {
        $data = $this->getRequestBody();
        $circuitData = $data['circuit'] ?? null;
        if (!$circuitData) {
            $this->errorResponse('Missing circuit data.');
            return;
        }
        $circuit = QuantumCircuit::fromArray($circuitData);
        $this->jsonResponse(['status' => 'success', 'qasm' => $circuit->exportToQASM()]);
    }

    /**
     * POST /api/analyse — run quantum information analysis.
     * Returns von Neumann entropy per qubit, Bloch sphere coordinates,
     * and circuit depth/gate statistics.
     */
    public function analyseCircuit(): void
    {
        $data = $this->getRequestBody();
        $circuitData = $data['circuit'] ?? null;
        if (!$circuitData) {
            $this->errorResponse('Missing circuit data.');
            return;
        }

        $circuit   = QuantumCircuit::fromArray($circuitData);
        $simulator = new QuantumSimulator();
        $result    = $simulator->run($circuit);
        $sv        = $result['statevector'];

        // Circuit statistics
        $gates       = $circuit->getGatesSorted();
        $gateCount   = count($gates);
        $depth       = $circuit->getMaxStep() + 1;
        $gateTypes   = array_count_values(array_column($gates, 'type'));

        $this->jsonResponse([
            'status'      => 'success',
            'entropy'     => $result['entropy'],
            'blochSpheres'=> $result['blochSpheres'],
            'dirac'       => $result['dirac'],
            'gateCount'   => $gateCount,
            'depth'       => $depth,
            'gateTypes'   => $gateTypes,
            'numQubits'   => $circuit->numQubits
        ]);
    }

    /**
     * POST /api/circuits/save — persist a circuit with a given name.
     */
    public function saveCircuit(): void
    {
        $data = $this->getRequestBody();
        $name = trim($data['name'] ?? '');
        $circuitData = $data['circuit'] ?? null;

        if ($name === '' || !$circuitData) {
            $this->errorResponse('Missing name or circuit data.');
            return;
        }

        $this->repository->save($name, $circuitData);
        $this->jsonResponse(['status' => 'success', 'message' => 'Circuit saved successfully.']);
    }

    /**
     * POST /api/circuits/delete — delete a circuit by name.
     */
    public function deleteCircuit(): void
    {
        $data = $this->getRequestBody();
        $name = trim($data['name'] ?? '');

        if ($name === '') {
            $this->errorResponse('Missing name parameter.');
            return;
        }

        $this->repository->deleteByName($name);
        $this->jsonResponse(['status' => 'success', 'message' => 'Circuit deleted successfully.']);
    }
}
