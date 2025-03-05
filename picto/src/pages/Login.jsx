import { useState } from 'react'
import { useNavigate } from 'react-router-dom'

export default function Login() {
  const [username, setUsername] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState(null)
  const [loading, setLoading] = useState(false)
  
  const navigate = useNavigate()

  async function handleSubmit(e) {
    e.preventDefault()
    setLoading(true)
    setError(null)

    try {
      const response = await fetch('https://www.casapicto.com/casapictov2/api/auth/login.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
          username, 
          password 
        })
      })
      
      if (!response.ok) {
        // The API returned an error (4xx or 5xx)
        setError('Credenciales inválidas o error en el servidor.')
      } else {
        const data = await response.json()
        // According to your README, data might have a token, e.g. data.token
        // Store the token in localStorage (or wherever you prefer)
        localStorage.setItem('authToken', data.token)

        // Navigate to the dashboard (protected page)
        navigate('/dashboard')
      }
    } catch (err) {
      setError('Error de conexión con el servidor.')
    }

    setLoading(false)
  }

  return (
    <div className="hero min-h-screen bg-base-200">
      <div className="hero-content flex-col">
        <div className="card w-96 bg-base-100 shadow-xl">
          <div className="card-body">
            <h1 className="text-2xl font-bold text-center mb-4">
              Iniciar sesión
            </h1>
            <form onSubmit={handleSubmit}>
              <div className="form-control mb-2">
                <label className="label font-semibold">
                  <span className="label-text">Usuario</span>
                </label>
                <input
                  type="text"
                  placeholder="admin"
                  className="input input-bordered"
                  value={username}
                  onChange={(e) => setUsername(e.target.value)}
                />
              </div>
              <div className="form-control mb-2">
                <label className="label font-semibold">
                  <span className="label-text">Contraseña</span>
                </label>
                <input
                  type="password"
                  placeholder="********"
                  className="input input-bordered"
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                />
              </div>
              {error && (
                <p className="text-red-600 my-2">{error}</p>
              )}
              <button
                type="submit"
                className={`btn btn-primary w-full mt-4 ${loading ? 'loading' : ''}`}
              >
                {loading ? 'Cargando...' : 'Iniciar Sesión'}
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>
  )
}
