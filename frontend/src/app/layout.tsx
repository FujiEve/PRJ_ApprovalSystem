import React from 'react';
import type { Metadata } from 'next';
import AppShell from '@/components/AppShell';
import './globals.css';

export const metadata: Metadata = {
  title: '稟議・承認ワークフローシステム',
  description: '稟議の申請・承認ワークスペース',
};

export default function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <html lang="ja">
      <body><AppShell>{children}</AppShell></body>
    </html>
  );
}

