'use client';

import { createContext, useContext, useEffect, useState } from 'react';
import { api, type Masters, type User } from '@/api';

interface WorkspaceValue {
  currentUserId: number;
  setCurrentUserId: (userId: number) => void;
  currentUser: User | null;
  masters: Masters | null;
  loadingMasters: boolean;
  mastersError: string;
}

const WorkspaceContext = createContext<WorkspaceValue | null>(null);

export function WorkspaceProvider({ children }: { children: React.ReactNode }) {
  const [currentUserId, setCurrentUserId] = useState(1);
  const [masters, setMasters] = useState<Masters | null>(null);
  const [loadingMasters, setLoadingMasters] = useState(true);
  const [mastersError, setMastersError] = useState('');

  useEffect(() => {
    const savedId = window.localStorage.getItem('approval-current-user-id');
    if (savedId && Number.isInteger(Number(savedId))) {
      setCurrentUserId(Number(savedId));
    }
  }, []);

  useEffect(() => {
    window.localStorage.setItem('approval-current-user-id', String(currentUserId));
    let active = true;
    setLoadingMasters(true);
    api.getMasters(currentUserId)
      .then((data) => {
        if (active) {
          setMasters(data);
          setMastersError('');
        }
      })
      .catch((error: unknown) => {
        if (active) {
          setMastersError(error instanceof Error ? error.message : '初期データを取得できませんでした。');
        }
      })
      .finally(() => {
        if (active) setLoadingMasters(false);
      });

    return () => {
      active = false;
    };
  }, [currentUserId]);

  const currentUser = masters?.users.find((user) => user.id === currentUserId) ?? null;

  return (
    <WorkspaceContext.Provider value={{
      currentUserId,
      setCurrentUserId,
      currentUser,
      masters,
      loadingMasters,
      mastersError,
    }}>
      {children}
    </WorkspaceContext.Provider>
  );
}

export function useWorkspace(): WorkspaceValue {
  const value = useContext(WorkspaceContext);
  if (!value) throw new Error('useWorkspace must be used within WorkspaceProvider');
  return value;
}