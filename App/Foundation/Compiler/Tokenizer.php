<?php

class DirectiveTokenizer
{
    const T_DIRECTIVE = 1;
    const T_OPEN_PAREN = 2;
    const T_CLOSE_PAREN = 3;
    const T_CONTENT = 4;
    const T_STRING = 5;
    const T_DIRECTIVE_END = 6;
    const T_COMMENT = 7;
    const T_ECHO = 8;
    const T_RAW_ECHO = 9;

    public function tokenize(string $input): array
    {
        $tokens = [];
        $length = strlen($input);
        $cursor = 0;

        while ($cursor < $length) {
            // Fast-forward through whitespace (without creating tokens)
            if (ctype_space($input[$cursor])) {
                $cursor++;
                continue;
            }

            // Match directives (@directive)
            if ($input[$cursor] === '@') {
                $start = $cursor;
                $cursor++;

                // Extract directive name
                $name = '';
                while ($cursor < $length && preg_match('/[a-zA-Z_]/', $input[$cursor])) {
                    $name .= $input[$cursor];
                    $cursor++;
                }

                // Check for argument-less directives
                if ($cursor < $length && ctype_space($input[$cursor])) {
                    $tokens[] = [self::T_DIRECTIVE, $name, $start];
                    continue;
                }

                // Check for parentheses
                if ($cursor < $length && $input[$cursor] === '(') {
                    $tokens[] = [self::T_DIRECTIVE, $name, $start];
                    $tokens[] = [self::T_OPEN_PAREN, '(', $cursor];
                    $cursor++;

                    // Find closing parenthesis
                    $depth = 1;
                    $stringStart = $cursor;
                    while ($cursor < $length && $depth > 0) {
                        if ($input[$cursor] === '(') {
                            $depth++;
                        } elseif ($input[$cursor] === ')') {
                            $depth--;
                        } elseif ($input[$cursor] === '"' || $input[$cursor] === "'") {
                            // Skip strings
                            $quote = $input[$cursor];
                            $cursor++;
                            while ($cursor < $length && $input[$cursor] !== $quote) {
                                if ($input[$cursor] === '\\') $cursor++;
                                $cursor++;
                            }
                        }
                        if ($depth > 0) $cursor++;
                    }

                    if ($depth === 0) {
                        $content = substr($input, $stringStart, $cursor - $stringStart);
                        $tokens[] = [self::T_CONTENT, $content, $stringStart];
                        $tokens[] = [self::T_CLOSE_PAREN, ')', $cursor];
                        $cursor++;
                    }
                    continue;
                }

                // Handle directive end markers (@enddirective)
                if (substr($input, $cursor, strlen($name)) === $name && str_starts_with($name, 'end')) {
                    $tokens[] = [self::T_DIRECTIVE_END, $name, $start];
                    $cursor += strlen($name);
                    continue;
                }

                // Simple directive without parentheses
                $tokens[] = [self::T_DIRECTIVE, $name, $start];
                continue;
            }

            // Match comments ({{-- --}})
            if (substr($input, $cursor, 4) === '{{--') {
                $start = $cursor;
                $cursor += 4;
                $endPos = strpos($input, '--}}', $cursor);
                if ($endPos !== false) {
                    $tokens[] = [self::T_COMMENT, substr($input, $cursor, $endPos - $cursor), $start];
                    $cursor = $endPos + 4;
                } else {
                    $cursor = $length;
                }
                continue;
            }

            // Match echos ({{ }}) and raw echos ({!! !!})
            if (substr($input, $cursor, 2) === '{{') {
                $start = $cursor;
                $cursor += 2;
                $endPos = strpos($input, '}}', $cursor);
                if ($endPos !== false) {
                    $tokens[] = [self::T_ECHO, substr($input, $cursor, $endPos - $cursor), $start];
                    $cursor = $endPos + 2;
                } else {
                    $cursor = $length;
                }
                continue;
            } elseif (substr($input, $cursor, 3) === '{!!') {
                $start = $cursor;
                $cursor += 3;
                $endPos = strpos($input, '!!}', $cursor);
                if ($endPos !== false) {
                    $tokens[] = [self::T_RAW_ECHO, substr($input, $cursor, $endPos - $cursor), $start];
                    $cursor = $endPos + 3;
                } else {
                    $cursor = $length;
                }
                continue;
            }

            // Everything else is regular content
            $start = $cursor;
            while ($cursor < $length && $input[$cursor] !== '@' && substr($input, $cursor, 2) !== '{{' && substr($input, $cursor, 3) !== '{!!') {
                $cursor++;
            }
            $tokens[] = [self::T_CONTENT, substr($input, $start, $cursor - $start), $start];
        }

        return $tokens;
    }
}

class DirectiveParser
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
            if ($this->current[0] === DirectiveTokenizer::T_DIRECTIVE) {
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
        $content = null;
        $endDirective = null;

        // Check for arguments
        if ($this->current && $this->current[0] === DirectiveTokenizer::T_OPEN_PAREN) {
            $this->advance(); // Skip opening paren

            // Get all content until closing paren
            $content = '';
            while ($this->current && $this->current[0] !== DirectiveTokenizer::T_CLOSE_PAREN) {
                $content .= $this->current[1];
                $this->advance();
            }

            if ($this->current && $this->current[0] === DirectiveTokenizer::T_CLOSE_PAREN) {
                $this->advance(); // Skip closing paren
            }
        }

        // Check for block content (until @end directive)
        if ($this->current && $this->current[0] === DirectiveTokenizer::T_CONTENT) {
            $content = $this->current[1];
            $this->advance();

            // Look for closing directive
            while ($this->current) {
                if (
                    $this->current[0] === DirectiveTokenizer::T_DIRECTIVE_END &&
                    str_replace('end', '', $this->current[1]) === $name
                ) {
                    $endDirective = $this->current;
                    $this->advance();
                    break;
                }
                $this->advance();
            }
        }

        return [
            'type' => 'directive',
            'name' => $name,
            'arguments' => trim($content ?? ''),
            'content' => $content,
            'endDirective' => $endDirective
        ];
    }

    private function advance()
    {
        $this->position++;
        $this->current = $this->tokens[$this->position] ?? null;
    }
}
