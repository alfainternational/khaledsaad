#!/usr/bin/env node
/**
 * مولّد التوكنز: من `design/tokens.json` إلى CSS وDart.
 *
 * السبب في وجوده: كان اللون يُكتب في CSS وفي Dart وفي القالب، فتغييرُه
 * يحتاج ثلاث تعديلات — ومن ينسى واحدًا لا يكتشف ذلك إلا على شاشة مستخدم.
 * المصدر واحد الآن، والباقي مُولَّد ويُرفض تعديله يدويًّا.
 *
 * الاستخدام:
 *   node scripts/build-tokens.mjs           # يكتب المولَّدات
 *   node scripts/build-tokens.mjs --check   # يفشل إن كانت المولَّدات قديمة
 */
import { readFileSync, writeFileSync, existsSync, mkdirSync } from 'node:fs';
import { dirname, resolve } from 'node:path';

const root = resolve(import.meta.dirname, '..');
const tokens = JSON.parse(readFileSync(resolve(root, 'design/tokens.json'), 'utf8'));
const check = process.argv.includes('--check');

const BANNER = (from) =>
  `/* مُولَّد من ${from} — لا تعدّله يدويًّا. عدّل المصدر وشغّل: npm run tokens:build */\n`;

const flat = (obj, prefix = '') =>
  Object.entries(obj).flatMap(([key, value]) => {
    if (key.startsWith('$')) return [];
    const name = prefix ? `${prefix}-${key}` : key;
    return typeof value === 'object' ? flat(value, name) : [[name, value]];
  });

/* ---------- CSS ---------- */
const cssVars = (entries, indent = '    ') =>
  entries.map(([k, v]) => `${indent}--${k}: ${v};`).join('\n');

const light = flat(tokens.color.light, 'color');
const dark = flat(tokens.color.dark, 'color');
const rest = [
  ...flat(tokens.space, 'space'),
  ...flat(tokens.radius, 'radius'),
  ...flat(tokens.shadow, 'shadow'),
  ...flat(tokens.font, 'font'),
  ...flat(tokens.breakpoints, 'bp'),
];

const css = `${BANNER('design/tokens.json')}
:root {
${cssVars([...rest, ...light])}
}

/* الوضع الداكن: تُعاد تعريف الأدوار وحدها، والقيم البنيوية تبقى. */
@media (prefers-color-scheme: dark) {
    :root:not([data-theme="light"]) {
${cssVars(dark, '        ')}
    }
}

:root[data-theme="dark"] {
${cssVars(dark)}
}
`;

/* ---------- Dart ---------- */
const dartName = (k) =>
  k.replace(/-(\w)/g, (_, c) => c.toUpperCase()).replace(/^(\d)/, 'n$1');

const hex = (v) => `Color(0xFF${v.replace('#', '').toUpperCase()})`;
const px = (v) => parseFloat(String(v));

const dart = `// مُولَّد من design/tokens.json — لا تعدّله يدويًّا.
// عدّل المصدر وشغّل: npm run tokens:build
import 'package:flutter/material.dart';

class DesignTokens {
  const DesignTokens._();

  // نقاط التوقّف — نفس القيم التي يقرأها الويب، فلا تنقسم الحقيقة.
${Object.entries(tokens.breakpoints).filter(([k]) => !k.startsWith('$')).map(([k, v]) => `  static const double bp${k.toUpperCase()} = ${px(v)};`).join('\n')}

  // المسافات
${Object.entries(tokens.space).filter(([k]) => !k.startsWith('$')).map(([k, v]) => `  static const double space${k} = ${px(v)};`).join('\n')}

  // نصف القطر
${Object.entries(tokens.radius).map(([k, v]) => `  static const double radius${k[0].toUpperCase()}${k.slice(1)} = ${px(v)};`).join('\n')}
}

class LightTokens {
  const LightTokens._();
${Object.entries(tokens.color.light).map(([k, v]) => `  static const Color ${dartName(k)} = ${hex(v)};`).join('\n')}
}

class DarkTokens {
  const DarkTokens._();
${Object.entries(tokens.color.dark).map(([k, v]) => `  static const Color ${dartName(k)} = ${hex(v)};`).join('\n')}
}
`;

const targets = [
  ['resources/css/tokens.css', css],
  ['mobile/lib/core/design/tokens.dart', dart],
];

let stale = [];

for (const [rel, content] of targets) {
  const path = resolve(root, rel);

  if (check) {
    if (!existsSync(path) || readFileSync(path, 'utf8') !== content) stale.push(rel);
    continue;
  }

  mkdirSync(dirname(path), { recursive: true });
  writeFileSync(path, content, 'utf8');
  console.log(`كُتب ${rel}`);
}

if (check) {
  if (stale.length) {
    console.error('مولَّدات لا تطابق design/tokens.json:\n  ' + stale.join('\n  '));
    console.error('شغّل: npm run tokens:build');
    process.exit(1);
  }
  console.log('المولَّدات مطابقة للمصدر.');
}
