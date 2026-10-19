import React from 'react';
import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: '稟議・承認ワークフローシステム',
  description: 'ローカル起動確認ページ',
};

export default function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <html lang="ja">
      <body
        style={{
          margin: 0,
          fontFamily: 'sans-serif',
          backgroundColor: '#f8fafc',
          color: '#0f172a',
        }}
      >
        {children}
      </body>
    </html>
  );
}

