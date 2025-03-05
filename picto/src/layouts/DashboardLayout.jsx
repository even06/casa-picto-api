import Sidebar from '../components/Sidebar'
import { Outlet } from 'react-router-dom'

export default function DashboardLayout() {
  return (
    <div className="flex min-h-screen">
      {/* Sidebar */}
      <Sidebar />

      {/* Main Content Area */}
      <div className="flex-1 p-4 bg-base-200">
        {/* Render whatever child route is active */}
        <Outlet />
      </div>
    </div>
  )
}
