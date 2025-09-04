export function isAuthenticated() {
  const token = localStorage.getItem("token")
  if (!token) return false

  try {
    // Basic token validation - check if it's not expired
    const payload = JSON.parse(atob(token.split(".")[1]))
    const currentTime = Date.now() / 1000

    if (payload.exp && payload.exp < currentTime) {
      localStorage.removeItem("token")
      return false
    }

    return true
  } catch (error) {
    // If token is malformed, remove it
    localStorage.removeItem("token")
    return false
  }
}

export function getToken() {
  return localStorage.getItem("token")
}

export function setToken(token) {
  localStorage.setItem("token", token)
}

export function removeToken() {
  localStorage.removeItem("token")
}

export function getUserFromToken() {
  const token = getToken()
  if (!token) return null

  try {
    const payload = JSON.parse(atob(token.split(".")[1]))
    return payload
  } catch (error) {
    return null
  }
}
