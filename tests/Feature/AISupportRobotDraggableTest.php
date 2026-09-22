<?php

use Illuminate\Support\Facades\View;

test('AI support robot component renders draggable launcher and independent chat panel', function () {
    View::share('cspNonce', 'test-csp-nonce-999');

    $html = View::make('components.ai-support-robot', [
        'activeShop' => 'test-store.myshopify.com',
    ])->render();

    // 1. Elements present
    expect($html)->toContain('id="zeosync-ai-support"');
    expect($html)->toContain('id="zeosyncAiLauncherWrapper"');
    expect($html)->toContain('id="zeosyncAiRobotBtn"');
    expect($html)->toContain('id="zeosyncAiChatPanel"');

    // 2. CSS draggable styles present
    expect($html)->toContain('touch-action: none;');
    expect($html)->toContain('user-select: none;');
    expect($html)->toContain('cursor: grab;');
    expect($html)->toContain('cursor: grabbing;');

    // 3. sessionStorage key and reload detection present in script
    expect($html)->toContain('zeosync_ai_chat_btn_pos');
    expect($html)->toContain("performance.getEntriesByType('navigation')");
    expect($html)->toContain("isReload");
    expect($html)->toContain("sessionStorage.removeItem(STORAGE_KEY)");

    // 4. Content boundary and relative coordinate clamping
    expect($html)->toContain("document.querySelector('.app-layout .content')");
    expect($html)->toContain("getBoundingClientRect()");
    expect($html)->toContain("clampedX");
    expect($html)->toContain("clampedY");

    // 5. Drag threshold and click suppression
    expect($html)->toContain("dist > 6");
    expect($html)->toContain("wasDragged");
    expect($html)->toContain("setPointerCapture");
    expect($html)->toContain("releasePointerCapture");
});
