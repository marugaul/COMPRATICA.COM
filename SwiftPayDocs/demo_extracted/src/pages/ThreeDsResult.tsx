import React, { useEffect, useState } from "react";
import { Api, token } from "../services/api";
import { useSearchParams } from "react-router-dom";

export const ThreeDsResult: React.FC = () => {

    const [searchParams] = useSearchParams();

    const success = searchParams.get("success");
    const uuid = searchParams.get("uuid") || "";

    const [response, setResponse] = useState("");
    const [loading, setLoading] = useState(true);
    const [errorMessage, setErrorMessage] = useState("");


    const query3ds = async () => {

        if (!uuid) {

            setErrorMessage("UUID no recibido en la URL");
            setLoading(false);
            return;

        }

        try {

            const result = await Api.query3ds(uuid);

            setResponse(
                JSON.stringify(result.data, null, 2)
            );

        } catch (e) {

            setErrorMessage("Error consultando resultado 3DS");

        }

        setLoading(false);

    };


    useEffect(() => {

        query3ds();

    }, []);



    return (

        <div className="container mt-5">

            <div className="row justify-content-center">

                <div className="col-md-8 col-lg-6">

                    <div className="card shadow">

                        <div className="card-header bg-primary text-white">

                            Resultado 3DS

                        </div>

                        <div className="card-body">

                            <div className="mb-3">

                                <strong>Success:</strong> {success ?? "No recibido"}

                            </div>

                            <div className="mb-3">

                                <strong>UUID:</strong> {uuid || "No recibido"}

                            </div>


                            {loading && (

                                <div className="alert alert-info">

                                    Consultando resultado 3DS...

                                </div>

                            )}


                            {errorMessage && (

                                <div className="alert alert-danger">

                                    {errorMessage}

                                </div>

                            )}


                            {response && (

                                <div className="card mt-3">

                                    <div className="card-header">

                                        Respuesta API

                                    </div>

                                    <div className="card-body">

                                        <pre style={{ fontSize: "13px" }}>
                                            {response}
                                        </pre>

                                    </div>

                                </div>

                            )}

                        </div>

                    </div>

                </div>

            </div>

        </div>

    );

};