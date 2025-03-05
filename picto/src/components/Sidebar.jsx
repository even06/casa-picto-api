import { NavLink } from 'react-router-dom'

export default function Sidebar() {
  return (
    <div className="w-64 bg-base-100 border-r border-base-300 min-h-screen p-4">
      <h2 className="text-xl font-bold mb-6">Casa Picto</h2>
      <ul className="menu menu-vertical">
        <li>
          <NavLink to="/dashboard" className="rounded-lg">
            Dashboard
          </NavLink>
        </li>
        <li>
          <NavLink to="/users" className="rounded-lg">
            Usuarios
          </NavLink>
        </li>
        {/* Future links: Pacientes, Profesionales, etc. */}
      </ul>
    </div>
  )
}
