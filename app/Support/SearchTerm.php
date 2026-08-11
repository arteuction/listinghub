<?php

declare(strict_types=1);

namespace App\Support;

/**
 * One definition of how a user-typed term becomes a bound LIKE pattern.
 *
 * Two things have to be true of every free-text search in the app, and both
 * were previously restated at each call site (and got restated wrong once):
 *
 *  1. WILDCARDS ARE LITERAL. A user searching for "50%" wants listings whose
 *     text contains "50%", not every listing. '!' is the escape character,
 *     not '\': a backslash inside a MySQL string literal escapes the closing
 *     quote, so ESCAPE '\' is a syntax error there while SQLite accepts it.
 *     '!' is literal on both. Whoever binds a pattern from here MUST declare
 *     ESCAPE '!' — use likeExpression() and the two cannot drift apart.
 *
 *  2. CASE IS IGNORED, INCLUDING FOR CYRILLIC. The platform's content is
 *     Bulgarian, so this is the common case rather than an edge one. The
 *     term is folded here and the column is folded by LOWER() in SQL. MySQL's
 *     LOWER() is Unicode-aware; SQLite's is ASCII-only, which is why
 *     SqliteUnicode replaces it on that driver. Folding only one side would
 *     silently match nothing.
 *
 * mb_strtolower is used rather than strtolower, which is byte-wise and would
 * leave "ПЕКАРНА" untouched while lowercasing "BAKERY".
 */
final class SearchTerm
{
    /** The LIKE escape character. Literal in both MySQL and SQLite string literals. */
    public const ESCAPE = '!';

    /**
     * A bound "contains" pattern: case-folded, with LIKE metacharacters and
     * the escape character itself neutralised.
     */
    public static function containsPattern(string $term): string
    {
        return '%'.self::escape(self::fold($term)).'%';
    }

    /**
     * The matching SQL fragment for a folded column, with one bound pattern.
     *
     * $column is a caller-supplied identifier and is interpolated, so it must
     * never come from request input — every current caller passes a literal.
     */
    public static function likeExpression(string $column): string
    {
        return 'LOWER('.$column.") LIKE ? ESCAPE '".self::ESCAPE."'";
    }

    /** Case-fold for comparison. Mirrors what LOWER() does to the column. */
    public static function fold(string $value): string
    {
        return mb_strtolower($value, 'UTF-8');
    }

    /**
     * Neutralise LIKE metacharacters. The escape character is doubled FIRST,
     * otherwise the escapes introduced for % and _ would themselves be escaped.
     */
    private static function escape(string $value): string
    {
        return str_replace(
            [self::ESCAPE, '%', '_'],
            [self::ESCAPE.self::ESCAPE, self::ESCAPE.'%', self::ESCAPE.'_'],
            $value,
        );
    }
}
