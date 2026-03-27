import { useNavigate } from "react-router-dom";

export default function Home() {
    const nav = useNavigate();

    const cardStyle = {
        height: "90px",
        width: "350px",
        borderRadius: "12px",
        display: "flex",
        alignItems: "center",
        justifyContent: "center",
        cursor: "pointer",
        //fontWeight: "bold",
        fontSize: "16px",
        marginBottom: "15px",
        border: "2px solid",
    };

    return (
        <div className="container mt-5">
            <h2 className="text-center mb-5">SwiftPay Demo  </h2>

            <div className="row g-3">
                <div className="col-md-4">
                    <div
                        className="text-primary"
                        style={{ ...cardStyle, borderColor: "#0d6efd" }}
                        onClick={() => nav("/validate")}
                    >
                        Validar Tarjeta
                    </div>
                </div>

                <div className="col-md-4">
                    <div
                        className="text-success"
                        style={{ ...cardStyle, borderColor: "#198754" }}
                        onClick={() => nav("/preauth")}
                    >
                        PreAutorización
                    </div>
                </div>

                <div className="col-md-4">
                    <div
                        className="text-info"
                        style={{ ...cardStyle, borderColor: "#0dcaf0" }}
                        onClick={() => nav("/auth")}
                    >
                        Autorización
                    </div>
                </div>

                <div className="col-md-4">
                    <div
                        className="text-warning"
                        style={{ ...cardStyle, borderColor: "#ffc107" }}
                        onClick={() => nav("/complete")}
                    >
                        Completitud
                    </div>
                </div>

                <div className="col-md-4">
                    <div
                        className="text-danger"
                        style={{ ...cardStyle, borderColor: "#dc3545" }}
                        onClick={() => nav("/void")}
                    >
                        Anulación
                    </div>
                </div>
            </div>
        </div>
    );
}