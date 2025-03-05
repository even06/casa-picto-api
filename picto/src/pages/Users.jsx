import { useState, useEffect } from 'react'

export default function Users() {
  const [users, setUsers] = useState([])

  // Fetch users on component mount
  useEffect(() => {
    fetchUsers()
  }, [])

  async function fetchUsers() {
    try {
      // Adjust the endpoint to match your API's actual path
      const token = localStorage.getItem('authToken')
      const response = await fetch('https://www.casapicto.com/casapictov2/api/users/list.php', {
        headers: {
          Authorization: `Bearer ${token}`
        }
      })

      if (response.ok) {
        const data = await response.json()
        setUsers(data) // assuming the API returns an array of users
      } else {
        console.error('Error fetching users')
      }
    } catch (error) {
      console.error('Error connecting to server', error)
    }
  }

  return (
    <div className="p-4">
      <h1 className="text-2xl font-bold mb-4">Usuarios</h1>
      {/* User list table or cards */}
      <div className="overflow-x-auto">
        <table className="table table-zebra w-full">
          <thead>
            <tr>
              <th>Usuario</th>
              <th>Rol</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            {users.map((user) => (
              <tr key={user.id}>
                <td>{user.username}</td>
                <td>{user.role}</td>
                <td>
                  {/* Buttons to Edit or Delete */}
                  <button className="btn btn-sm btn-warning mr-2">Editar</button>
                  <button className="btn btn-sm btn-error">Eliminar</button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  )
}
