<?php
class Parserd
{
    private $tokens;
    private $position = 0;
    private $current;

    public function parse(array $tokens): array
    {
        $this->tokens = $tokens;
        $this->position = 0;
        $this->current = $tokens[0] ?? null;

        $directives = [];
        while ($this->current) {
            if ($this->current[0] === Tokenizer::T_DIRECTIVE) {
                $directives[] = $this->parseDirective();
            } else {
                $this->advance();
            }
        }

        return $directives;
    }

    private function parseDirective(): array
    {
        $name = $this->current[1];
        $startPos = $this->current[2];
        $this->advance();

        $arguments = [];
        if ($this->current && $this->current[0] === Tokenizer::T_OPEN_PAREN) {
            $openParenPos = $this->current[2];
            $this->advance();

            // Find the closing parenthesis position
            $closeParenPos = $this->findClosingParenthesisPosition($openParenPos);

            // Extract the entire arguments string between parentheses
            $argumentsStr = substr($this->tokens[0][1], $openParenPos + 1, $closeParenPos - $openParenPos - 1);

            // Split arguments while preserving the original string structure
            $arguments = $this->splitArgumentsPreservingOriginal($argumentsStr);

            // Advance to the closing parenthesis
            while ($this->current && $this->current[2] <= $closeParenPos) {
                $this->advance();
            }
        }

        return [
            'type' => 'directive',
            'name' => $name,
            'arguments' => $arguments
        ];
    }

    private function findClosingParenthesisPosition($openPos): int
    {
        $depth = 1;
        $pos = $openPos + 1;
        $length = strlen($this->tokens[0][1]);
        
        while ($pos < $length && $depth > 0) {
            $char = $this->tokens[0][1][$pos];
            // echo $char;
            
            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
            }
            $pos++;
        }

        if ($depth !== 0) {
            throw new RuntimeException("Unmatched parentheses starting at position {$this->tokens[0][1]}");
        }

        return $pos - 1;
    }

    private function splitArgumentsPreservingOriginal(string $argumentsStr): array
    {
        $arguments = [];
        $currentArg = '';
        $depth = 0;
        $length = strlen($argumentsStr);

        for ($i = 0; $i < $length; $i++) {
            $char = $argumentsStr[$i];

            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
            }

            if ($char === ',' && $depth === 0) {
                $arguments[] = $currentArg;
                $currentArg = '';
            } else {
                $currentArg .= $char;
            }
        }

        if ($currentArg !== '') {
            $arguments[] = $currentArg;
        }

        return $arguments;
    }

    private function advance()
    {
        $this->position++;
        $this->current = $this->tokens[$this->position] ?? null;
    }
}
