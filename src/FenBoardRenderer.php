<?php

declare(strict_types=1);

namespace MarkBase;

final class FenBoardRenderer
{
    private const PIECES = [
        'K' => ['label' => 'white king', 'type' => 'king', 'color' => 'white'],
        'Q' => ['label' => 'white queen', 'type' => 'queen', 'color' => 'white'],
        'R' => ['label' => 'white rook', 'type' => 'rook', 'color' => 'white'],
        'B' => ['label' => 'white bishop', 'type' => 'bishop', 'color' => 'white'],
        'N' => ['label' => 'white knight', 'type' => 'knight', 'color' => 'white'],
        'P' => ['label' => 'white pawn', 'type' => 'pawn', 'color' => 'white'],
        'k' => ['label' => 'black king', 'type' => 'king', 'color' => 'black'],
        'q' => ['label' => 'black queen', 'type' => 'queen', 'color' => 'black'],
        'r' => ['label' => 'black rook', 'type' => 'rook', 'color' => 'black'],
        'b' => ['label' => 'black bishop', 'type' => 'bishop', 'color' => 'black'],
        'n' => ['label' => 'black knight', 'type' => 'knight', 'color' => 'black'],
        'p' => ['label' => 'black pawn', 'type' => 'pawn', 'color' => 'black'],
    ];

    private const SVG_NS = 'http://www.w3.org/2000/svg';
    private const BOARD_SIZE = 480;
    private const SQUARE_SIZE = 60;
    private const PIECE_SIZE = 56;

    private string $basePath;

    public function __construct(string $basePath = '')
    {
        $this->basePath = rtrim($basePath, '/');
    }

    /**
     * @return array{valid: bool, error: string, squares?: array<int, array<int, string|null>>, active?: string|null, normalized?: string}
     */
    public function parse(string $fen): array
    {
        $normalized = trim(preg_replace('/\s+/', ' ', $fen) ?? $fen);
        if ($normalized === '') {
            return ['valid' => false, 'error' => 'FEN is empty.'];
        }

        $fields = preg_split('/\s+/', $normalized) ?: [];
        if (count($fields) !== 1 && count($fields) !== 6) {
            return ['valid' => false, 'error' => 'FEN must contain board placement only or all six standard fields.'];
        }

        if (count($fields) === 6) {
            $fieldError = $this->validateFields($fields);
            if ($fieldError !== null) {
                return ['valid' => false, 'error' => $fieldError];
            }
        }

        $ranks = explode('/', $fields[0]);
        if (count($ranks) !== 8) {
            return ['valid' => false, 'error' => 'Board placement must contain eight ranks.'];
        }

        $squares = [];
        foreach ($ranks as $rankIndex => $rankText) {
            $rankSquares = [];
            $fileCount = 0;
            $length = strlen($rankText);
            for ($i = 0; $i < $length; $i++) {
                $char = $rankText[$i];
                if (isset(self::PIECES[$char])) {
                    $rankSquares[] = $char;
                    $fileCount++;
                    continue;
                }
                if ($char >= '1' && $char <= '8') {
                    $emptyCount = (int) $char;
                    for ($j = 0; $j < $emptyCount; $j++) {
                        $rankSquares[] = null;
                    }
                    $fileCount += $emptyCount;
                    continue;
                }
                return ['valid' => false, 'error' => 'Board placement contains an invalid piece or digit.'];
            }

            if ($fileCount !== 8) {
                return ['valid' => false, 'error' => 'Each rank must contain exactly eight squares.'];
            }
            $squares[$rankIndex] = $rankSquares;
        }

        return [
            'valid' => true,
            'error' => '',
            'squares' => $squares,
            'active' => $fields[1] ?? null,
            'normalized' => $normalized,
        ];
    }

    public function render(\DOMDocument $dom, string $fen): ?\DOMElement
    {
        $parsed = $this->parse($fen);
        if (!$parsed['valid']) {
            return null;
        }

        $figure = $dom->createElement('figure');
        $figure->setAttribute('class', 'fen-board');
        $figure->setAttribute('data-fen', $parsed['normalized'] ?? '');

        $board = $dom->createElementNS(self::SVG_NS, 'svg');
        $board->setAttribute('class', 'fen-board__svg');
        $board->setAttribute('viewBox', '0 0 ' . self::BOARD_SIZE . ' ' . self::BOARD_SIZE);
        $board->setAttribute('role', 'img');
        $board->setAttribute('aria-label', $this->ariaBoardLabel($parsed['active'] ?? null));

        $title = $dom->createElementNS(self::SVG_NS, 'title');
        $title->appendChild($dom->createTextNode($this->ariaBoardLabel($parsed['active'] ?? null)));
        $board->appendChild($title);

        $files = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h'];
        foreach ($parsed['squares'] ?? [] as $rankIndex => $rankSquares) {
            foreach ($rankSquares as $fileIndex => $piece) {
                $squareName = $files[$fileIndex] . (string) (8 - $rankIndex);
                $colorClass = (($rankIndex + $fileIndex) % 2 === 0) ? 'light' : 'dark';
                $x = $fileIndex * self::SQUARE_SIZE;
                $y = $rankIndex * self::SQUARE_SIZE;

                $square = $dom->createElementNS(self::SVG_NS, 'g');
                $square->setAttribute('class', 'fen-board__square fen-board__square--' . $colorClass);
                $square->setAttribute('role', 'img');
                $square->setAttribute('data-square', $squareName);
                $square->setAttribute('aria-label', $this->ariaSquareLabel($squareName, $piece));

                $rect = $dom->createElementNS(self::SVG_NS, 'rect');
                $rect->setAttribute('x', (string) $x);
                $rect->setAttribute('y', (string) $y);
                $rect->setAttribute('width', (string) self::SQUARE_SIZE);
                $rect->setAttribute('height', (string) self::SQUARE_SIZE);
                $rect->setAttribute('class', 'fen-board__rect');
                $square->appendChild($rect);

                if ($piece !== null) {
                    $pieceOffset = (self::SQUARE_SIZE - self::PIECE_SIZE) / 2;
                    $pieceNode = $dom->createElementNS(self::SVG_NS, 'image');
                    $pieceNode->setAttribute('x', (string) ($x + $pieceOffset));
                    $pieceNode->setAttribute('y', (string) ($y + $pieceOffset));
                    $pieceNode->setAttribute('width', (string) self::PIECE_SIZE);
                    $pieceNode->setAttribute('height', (string) self::PIECE_SIZE);
                    $pieceNode->setAttribute('class', 'fen-board__piece');
                    $pieceNode->setAttribute('href', $this->pieceAssetUrl($piece));
                    $pieceNode->setAttribute('preserveAspectRatio', 'xMidYMid meet');
                    $pieceNode->setAttribute('aria-hidden', 'true');
                    $square->appendChild($pieceNode);
                }

                $board->appendChild($square);
            }
        }

        $caption = $dom->createElement('figcaption');
        $caption->setAttribute('class', 'visually-hidden');
        $caption->appendChild($dom->createTextNode('Chess position from FEN: ' . ($parsed['normalized'] ?? '')));

        $figure->appendChild($board);
        $figure->appendChild($caption);
        return $figure;
    }

    /**
     * @param array<int, string> $fields
     */
    private function validateFields(array $fields): ?string
    {
        if (!in_array($fields[1], ['w', 'b'], true)) {
            return 'Active color must be w or b.';
        }
        if ($fields[2] !== '-' && !preg_match('/^K?Q?k?q?$/', $fields[2])) {
            return 'Castling availability is invalid.';
        }
        if ($fields[2] === '') {
            return 'Castling availability is invalid.';
        }
        if ($fields[3] !== '-' && !preg_match('/^[a-h][36]$/', $fields[3])) {
            return 'En passant square is invalid.';
        }
        if (!preg_match('/^\d+$/', $fields[4])) {
            return 'Halfmove clock must be a non-negative integer.';
        }
        if (!preg_match('/^[1-9]\d*$/', $fields[5])) {
            return 'Fullmove number must be a positive integer.';
        }
        return null;
    }

    private function ariaBoardLabel(?string $active): string
    {
        if ($active === 'w') {
            return 'Chessboard from FEN, white to move';
        }
        if ($active === 'b') {
            return 'Chessboard from FEN, black to move';
        }
        return 'Chessboard from FEN';
    }

    private function ariaSquareLabel(string $squareName, ?string $piece): string
    {
        if ($piece === null) {
            return $squareName . ' empty';
        }
        return $squareName . ' ' . self::PIECES[$piece]['label'];
    }

    private function pieceAssetUrl(string $piece): string
    {
        $pieceData = self::PIECES[$piece];
        return $this->basePath . '/img-internal/chess-pieces/sashite/' . $pieceData['color'] . '/' . $pieceData['type'] . '.svg';
    }
}
