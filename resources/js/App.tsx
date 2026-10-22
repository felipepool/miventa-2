import { useEffect, useState } from 'react';
import { Route, Routes } from 'react-router-dom';
import Spinner from 'react-bootstrap/Spinner';
import AppLayout from '@/layouts/AppLayout';
import HomePage from '@/pages/HomePage';
import NotFoundPage from '@/pages/NotFoundPage';
import { http } from '@/lib/http';
import type { User } from '@/types/user';

export default function App() {
    const [user, setUser] = useState<User | null>(null);

    useEffect(() => {
        http.get<{ data: User }>('/me').then((response) => setUser(response.data.data));
    }, []);

    if (!user) {
        return (
            <div className="d-flex justify-content-center py-5">
                <Spinner animation="border" role="status">
                    <span className="visually-hidden">Cargando…</span>
                </Spinner>
            </div>
        );
    }

    return (
        <Routes>
            <Route element={<AppLayout user={user} />}>
                <Route index element={<HomePage user={user} />} />
                <Route path="*" element={<NotFoundPage />} />
            </Route>
        </Routes>
    );
}
