<?php

use App\Services\SummaryEvaluationService;

beforeEach(function () {
    $this->svc = new SummaryEvaluationService;
});

it('scores identical texts as perfect 1.0', function () {
    $score = $this->svc->scorePair('- staff superb and polite', '- staff superb and polite');

    expect($score['r1']['f1'])->toBe(1.0)
        ->and($score['r2']['f1'])->toBe(1.0)
        ->and($score['rl']['f1'])->toBe(1.0);
});

it('computes known ROUGE values for a partial overlap', function () {
    // candidate tokens: the cat sat (3) | reference tokens: the cat sat on the mat (6)
    // R1: overlap 3 → R=0.5 P=1.0 F1=0.6667 | R2: overlap 2/5 → R=0.4 P=1.0 F1=0.5714
    $score = $this->svc->scorePair('the cat sat', 'the cat sat on the mat');

    expect($score['r1']['recall'])->toBe(0.5)
        ->and($score['r1']['precision'])->toBe(1.0)
        ->and($score['r1']['f1'])->toBe(0.6667)
        ->and($score['r2']['recall'])->toBe(0.4)
        ->and($score['r2']['precision'])->toBe(1.0)
        ->and($score['r2']['f1'])->toBe(0.5714)
        ->and($score['rl']['f1'])->toBe(0.6667);
});

it('returns zeros for empty candidate', function () {
    $score = $this->svc->scorePair('', 'some reference text');

    expect($score['r1']['f1'])->toBe(0.0)
        ->and($score['r2']['f1'])->toBe(0.0)
        ->and($score['rl']['f1'])->toBe(0.0);
});

it('macro-averages F1 across pairs', function () {
    $pairs = [
        $this->svc->scorePair('the cat sat', 'the cat sat on the mat'),
        $this->svc->scorePair('hello world', 'hello world'),
    ];

    $macro = $this->svc->macroF1($pairs);

    expect($macro['n'])->toBe(2)
        ->and($macro['r1'])->toBe(round((0.6667 + 1.0) / 2, 4))
        ->and($macro['rl'])->toBe(round((0.6667 + 1.0) / 2, 4));
});

it('parses hotel reference sections from md', function () {
    $path = tempnam(sys_get_temp_dir(), 'refs').'.md';
    file_put_contents($path, "## Hotel Alpha (10 reviews, 5★)\n\n- Staff superb.\n- Great pool.\n\n## Hotel Beta (10 reviews)\n\n- Nice beach.\n");

    $refs = $this->svc->parseReferencesMd($path);
    unlink($path);

    expect($refs)->toHaveCount(2)
        ->and($refs['Hotel Alpha'])->toContain('Staff superb.')
        ->and($refs['Hotel Beta'])->toContain('Nice beach.');
});
