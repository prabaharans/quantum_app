<?php

declare(strict_types=1);

namespace QuantumApp;

/**
 * Represents a complex number (a + bi) and provides basic arithmetic operations.
 */
class Complex
{
    public function __construct(
        public float $real,
        public float $imag = 0.0
    ) {}

    /**
     * Create a complex number from polar coordinates (r * e^(i * theta)).
     */
    public static function fromPolar(float $r, float $theta): self
    {
        return new self($r * cos($theta), $r * sin($theta));
    }

    public function add(self|float $other): self
    {
        if (is_float($other)) {
            return new self($this->real + $other, $this->imag);
        }
        return new self($this->real + $other->real, $this->imag + $other->imag);
    }

    public function sub(self|float $other): self
    {
        if (is_float($other)) {
            return new self($this->real - $other, $this->imag);
        }
        return new self($this->real - $other->real, $this->imag - $other->imag);
    }

    public function mul(self|float $other): self
    {
        if (is_float($other)) {
            return new self($this->real * $other, $this->imag * $other);
        }
        return new self(
            $this->real * $other->real - $this->imag * $other->imag,
            $this->real * $other->imag + $this->imag * $other->real
        );
    }

    public function conjugate(): self
    {
        return new self($this->real, -$this->imag);
    }

    public function magnitude(): float
    {
        return sqrt($this->real ** 2 + $this->imag ** 2);
    }

    public function magnitudeSquared(): float
    {
        return $this->real ** 2 + $this->imag ** 2;
    }

    /**
     * Return the phase (argument) of the complex number in radians, from -pi to pi.
     */
    public function phase(): float
    {
        return atan2($this->imag, $this->real);
    }

    public function __toString(): string
    {
        if (abs($this->imag) < 1e-9) {
            return sprintf('%.4f', $this->real);
        }
        if (abs($this->real) < 1e-9) {
            return sprintf('%.4fi', $this->imag);
        }
        return sprintf('%.4f %s %.4fi', $this->real, $this->imag >= 0 ? '+' : '-', abs($this->imag));
    }
}
