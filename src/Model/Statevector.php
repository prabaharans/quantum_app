<?php

declare(strict_types=1);

namespace QuantumApp\Model;

/**
 * Manages the statevector of a multi-qubit system.
 * Employs a flat float array representation for real/imaginary parts of amplitudes for speed.
 */
class Statevector
{
    public readonly int $numQubits;
    /** @var array<int, float> Flat array of size 2^(n+1), where index 2i is real part and 2i+1 is imaginary part of state i */
    public array $amplitudes;

    public function __construct(int $numQubits)
    {
        if ($numQubits < 1 || $numQubits > 12) {
            throw new \InvalidArgumentException('Number of qubits must be between 1 and 12.');
        }

        $this->numQubits = $numQubits;
        $numStates = 1 << $numQubits;
        
        // Initialize statevector to |00...0>
        $this->amplitudes = array_fill(0, $numStates * 2, 0.0);
        $this->amplitudes[0] = 1.0; // Real part of |00...0> is 1.0
    }

    /**
     * Set the statevector to a specific amplitude array (for testing/custom states).
     */
    public function setAmplitudes(array $amplitudes): void
    {
        $expectedSize = (1 << $this->numQubits) * 2;
        if (count($amplitudes) !== $expectedSize) {
            throw new \InvalidArgumentException("Invalid amplitudes array size. Expected {$expectedSize} elements.");
        }
        $this->amplitudes = $amplitudes;
    }

    /**
     * Check if the control qubits are all in state |1> for a given state index.
     */
    private function areControlsSatisfied(int $index, ?array $controls): bool
    {
        if ($controls === null || empty($controls)) {
            return true;
        }
        foreach ($controls as $controlQubit) {
            if ((($index >> $controlQubit) & 1) === 0) {
                return false;
            }
        }
        return true;
    }

    /**
     * Apply a general 2x2 unitary matrix to a target qubit.
     * $u matrix is of the form:
     * [
     *   [u00_real, u00_imag, u01_real, u01_imag],
     *   [u10_real, u10_imag, u11_real, u11_imag]
     * ]
     */
    public function applyOneQubitGate(int $target, array $u, ?array $controls = null): void
    {
        $n = $this->numQubits;
        $halfStates = 1 << ($n - 1);

        $u00_r = (float)$u[0][0]; $u00_i = (float)$u[0][1];
        $u01_r = (float)$u[0][2]; $u01_i = (float)$u[0][3];
        $u10_r = (float)$u[1][0]; $u10_i = (float)$u[1][1];
        $u11_r = (float)$u[1][2]; $u11_i = (float)$u[1][3];

        $prev = $this->amplitudes;

        for ($j = 0; $j < $halfStates; $j++) {
            // Insert 0 at target position to form i0, then flip target to 1 to form i1
            $i0 = (($j >> $target) << ($target + 1)) | ($j & ((1 << $target) - 1));
            $i1 = $i0 | (1 << $target);

            // If controls are specified and NOT satisfied for this pair, we skip the gate
            if ($controls !== null && !empty($controls)) {
                if (!$this->areControlsSatisfied($i0, $controls)) {
                    continue;
                }
            }

            $r0 = $prev[2 * $i0];
            $im0 = $prev[2 * $i0 + 1];
            $r1 = $prev[2 * $i1];
            $im1 = $prev[2 * $i1 + 1];

            // psi'_i0 = u00 * psi_i0 + u01 * psi_i1
            $this->amplitudes[2 * $i0]     = $u00_r * $r0 - $u00_i * $im0 + $u01_r * $r1 - $u01_i * $im1;
            $this->amplitudes[2 * $i0 + 1] = $u00_r * $im0 + $u00_i * $r0 + $u01_r * $im1 + $u01_i * $r1;

            // psi'_i1 = u10 * psi_i0 + u11 * psi_i1
            $this->amplitudes[2 * $i1]     = $u10_r * $r0 - $u10_i * $im0 + $u11_r * $r1 - $u11_i * $im1;
            $this->amplitudes[2 * $i1 + 1] = $u10_r * $im0 + $u10_i * $r0 + $u11_r * $im1 + $u11_i * $r1;
        }
    }

    /**
     * Swap the states of two qubits.
     */
    public function applySwap(int $qubitA, int $qubitB, ?array $controls = null): void
    {
        $n = $this->numQubits;
        $numStates = 1 << $n;
        $prev = $this->amplitudes;

        for ($i = 0; $i < $numStates; $i++) {
            $bitA = ($i >> $qubitA) & 1;
            $bitB = ($i >> $qubitB) & 1;

            // Only swap on one branch to avoid double swapping
            if ($bitA === 0 && $bitB === 1) {
                $j = $i | (1 << $qubitA);
                $j = $j & ~(1 << $qubitB);

                if ($controls !== null && !empty($controls)) {
                    if (!$this->areControlsSatisfied($i, $controls)) {
                        continue;
                    }
                }

                // Swap indices i and j
                $this->amplitudes[2 * $i] = $prev[2 * $j];
                $this->amplitudes[2 * $i + 1] = $prev[2 * $j + 1];

                $this->amplitudes[2 * $j] = $prev[2 * $i];
                $this->amplitudes[2 * $j + 1] = $prev[2 * $i + 1];
            }
        }
    }

    /**
     * Measure a qubit, collapsing the statevector and returning the outcome (0 or 1).
     */
    public function measure(int $qubit): int
    {
        $p0 = 0.0;
        $numStates = 1 << $this->numQubits;
        
        for ($i = 0; $i < $numStates; $i++) {
            if ((($i >> $qubit) & 1) === 0) {
                $r = $this->amplitudes[2 * $i];
                $im = $this->amplitudes[2 * $i + 1];
                $p0 += $r * $r + $im * $im;
            }
        }

        // Determine outcome based on p0
        $rand = (float)random_int(0, 1000000) / 1000000.0;
        $outcome = ($rand < $p0) ? 0 : 1;
        $p_outcome = $outcome === 0 ? $p0 : (1.0 - $p0);

        if ($p_outcome < 1e-15) {
            // Edge case: if outcome probability is zero but selected due to floating precision
            $p_outcome = 1.0;
        }

        // Collapse statevector
        $norm = sqrt($p_outcome);
        for ($i = 0; $i < $numStates; $i++) {
            $bit = ($i >> $qubit) & 1;
            if ($bit === $outcome) {
                $this->amplitudes[2 * $i] /= $norm;
                $this->amplitudes[2 * $i + 1] /= $norm;
            } else {
                $this->amplitudes[2 * $i] = 0.0;
                $this->amplitudes[2 * $i + 1] = 0.0;
            }
        }

        return $outcome;
    }

    /**
     * Get probabilities of all computational basis states.
     * @return array<int, float>
     */
    public function getProbabilities(): array
    {
        $numStates = 1 << $this->numQubits;
        $probs = [];
        for ($i = 0; $i < $numStates; $i++) {
            $r = $this->amplitudes[2 * $i];
            $im = $this->amplitudes[2 * $i + 1];
            $probs[$i] = $r * $r + $im * $im;
        }
        return $probs;
    }

    /**
     * Get 3D Bloch Sphere coordinates (x, y, z) of a specific qubit.
     * This takes the partial trace of the density matrix for the given qubit,
     * representing the state exactly, even under entanglement.
     * @return array{x: float, y: float, z: float}
     */
    public function getBlochSphereCoordinates(int $qubit): array
    {
        $n = $this->numQubits;
        $halfStates = 1 << ($n - 1);

        $rho00 = 0.0;
        $rho11 = 0.0;
        $rho01_real = 0.0;
        $rho01_imag = 0.0;

        for ($j = 0; $j < $halfStates; $j++) {
            $i0 = (($j >> $qubit) << ($qubit + 1)) | ($j & ((1 << $qubit) - 1));
            $i1 = $i0 | (1 << $qubit);

            $r0 = $this->amplitudes[2 * $i0];
            $im0 = $this->amplitudes[2 * $i0 + 1];
            $r1 = $this->amplitudes[2 * $i1];
            $im1 = $this->amplitudes[2 * $i1 + 1];

            $rho00 += $r0 * $r0 + $im0 * $im0;
            $rho11 += $r1 * $r1 + $im1 * $im1;

            // rho_01 = psi_i0 * conj(psi_i1)
            // (r0 + im0*i) * (r1 - im1*i) = (r0*r1 + im0*im1) + (im0*r1 - r0*im1)i
            $rho01_real += $r0 * $r1 + $im0 * $im1;
            $rho01_imag += $im0 * $r1 - $r0 * $im1;
        }

        // x = 2 * Re(rho_01), y = -2 * Im(rho_01), z = rho_00 - rho_11
        $x = 2.0 * $rho01_real;
        $y = -2.0 * $rho01_imag;
        $z = $rho00 - $rho11;

        // Clean values close to zero
        if (abs($x) < 1e-9) $x = 0.0;
        if (abs($y) < 1e-9) $y = 0.0;
        if (abs($z) < 1e-9) $z = 0.0;

        return ['x' => $x, 'y' => $y, 'z' => $z];
    }

    /**
     * Formats the statevector as Dirac/Ket notation.
     */
    public function getDiracNotation(float $threshold = 1e-4): string
    {
        $numStates = 1 << $this->numQubits;
        $terms = [];
        for ($i = 0; $i < $numStates; $i++) {
            $r = $this->amplitudes[2 * $i];
            $im = $this->amplitudes[2 * $i + 1];
            $magSq = $r * $r + $im * $im;
            
            if ($magSq > $threshold) {
                $binary = str_pad(decbin($i), $this->numQubits, '0', STR_PAD_LEFT);
                
                $coeff = '';
                if (abs($im) < 1e-9) {
                    if (abs($r - 1.0) < 1e-9) {
                        $coeff = '';
                    } elseif (abs($r - (-1.0)) < 1e-9) {
                        $coeff = '-';
                    } else {
                        $coeff = sprintf('%.4f', $r);
                    }
                } elseif (abs($r) < 1e-9) {
                    if (abs($im - 1.0) < 1e-9) {
                        $coeff = 'i';
                    } elseif (abs($im - (-1.0)) < 1e-9) {
                        $coeff = '-i';
                    } else {
                        $coeff = sprintf('%.4fi', $im);
                    }
                } else {
                    $coeff = sprintf('(%.4f %s %.4fi)', $r, $im >= 0 ? '+' : '-', abs($im));
                }
                
                $term = $coeff . '|' . $binary . '⟩';
                $terms[] = $term;
            }
        }

        if (empty($terms)) {
            return '0';
        }

        $result = implode(' + ', $terms);
        $result = str_replace('+ -', '- ', $result);
        
        return $result;
    }

    /**
     * Calculate von Neumann entropy S = -Tr(ρ log₂ ρ) of the reduced density
     * matrix for a given qubit (computed from the partial trace).
     * Returns 0 for a pure state and 1 for a maximally mixed (entangled) qubit.
     */
    public function getVonNeumannEntropy(int $qubit): float
    {
        $n = $this->numQubits;
        $halfStates = 1 << ($n - 1);

        $rho00 = 0.0;
        $rho11 = 0.0;
        $rho01_real = 0.0;
        $rho01_imag = 0.0;

        for ($j = 0; $j < $halfStates; $j++) {
            $i0 = (($j >> $qubit) << ($qubit + 1)) | ($j & ((1 << $qubit) - 1));
            $i1 = $i0 | (1 << $qubit);

            $r0  = $this->amplitudes[2 * $i0];
            $im0 = $this->amplitudes[2 * $i0 + 1];
            $r1  = $this->amplitudes[2 * $i1];
            $im1 = $this->amplitudes[2 * $i1 + 1];

            $rho00      += $r0 * $r0 + $im0 * $im0;
            $rho11      += $r1 * $r1 + $im1 * $im1;
            $rho01_real += $r0 * $r1 + $im0 * $im1;
            $rho01_imag += $im0 * $r1 - $r0 * $im1;
        }

        // Eigenvalues of 2×2 Hermitian reduced density matrix:
        // λ± = (1 ± sqrt((rho00-rho11)² + 4|rho01|²)) / 2
        $offDiagMagSq = $rho01_real * $rho01_real + $rho01_imag * $rho01_imag;
        $discriminant = ($rho00 - $rho11) ** 2 + 4.0 * $offDiagMagSq;
        $sqrtD = sqrt(max(0.0, $discriminant));

        $lambda1 = 0.5 * (1.0 + $sqrtD);
        $lambda2 = 0.5 * (1.0 - $sqrtD);

        // S = -sum(λ log₂ λ)
        $entropy = 0.0;
        foreach ([$lambda1, $lambda2] as $lambda) {
            if ($lambda > 1e-12) {
                $entropy -= $lambda * log($lambda) / log(2.0);
            }
        }

        return max(0.0, $entropy);
    }

    /**
     * Compute fidelity F = |⟨ψ|φ⟩|² between this statevector and another.
     * Returns 1.0 if states are identical, 0.0 if orthogonal.
     */
    public function getFidelity(self $other): float
    {
        if ($this->numQubits !== $other->numQubits) {
            throw new \InvalidArgumentException('Statevectors must have the same number of qubits.');
        }

        $numStates = 1 << $this->numQubits;
        $innerReal = 0.0;
        $innerImag = 0.0;

        for ($i = 0; $i < $numStates; $i++) {
            $r1  = $this->amplitudes[2 * $i];
            $im1 = $this->amplitudes[2 * $i + 1];
            $r2  = $other->amplitudes[2 * $i];
            $im2 = $other->amplitudes[2 * $i + 1];

            // ⟨ψ|φ⟩ += conj(psi_i) * phi_i = (r1 - im1*i)(r2 + im2*i)
            $innerReal += $r1 * $r2 + $im1 * $im2;
            $innerImag += $r1 * $im2 - $im1 * $r2;
        }

        return $innerReal * $innerReal + $innerImag * $innerImag;
    }

    /**
     * Get the full raw amplitude data as an array of [real, imag] pairs.
     * @return array<int, array{real: float, imag: float, prob: float, phase: float}>
     */
    public function getAmplitudeData(): array
    {
        $numStates = 1 << $this->numQubits;
        $data = [];
        for ($i = 0; $i < $numStates; $i++) {
            $r  = $this->amplitudes[2 * $i];
            $im = $this->amplitudes[2 * $i + 1];
            $data[] = [
                'real'  => $r,
                'imag'  => $im,
                'prob'  => $r * $r + $im * $im,
                'phase' => atan2($im, $r)  // radians [-π, π]
            ];
        }
        return $data;
    }
}
