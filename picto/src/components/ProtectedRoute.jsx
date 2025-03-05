import { Navigate, Outlet } from 'react-router-dom'

export default function ProtectedRoute() {
  const token = localStorage.getItem('authToken')
  return token ? <Outlet /> : <Navigate to="/" replace />
}
